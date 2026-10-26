<?php

namespace tournoi_enoa\Core;

/**
 * Reconstruit l'arbre de la phase finale (upper bracket / lower bracket / grande finale) à
 * partir d'une liste plate de matchs chaînés via next_match_id/next_match_slot, et le restitue
 * en HTML sous forme de colonnes de rounds (une carte par match, une colonne par round, reliées
 * par des lignes de connexion) — les deux tableaux convergent naturellement vers la grande finale.
 */
class BracketTree
{
    /**
     * @param iterable<object> $matches matchs phase in ('upper','lower','grand_final') d'un tournoi
     * @return array{upper: array|null, lower: array|null, grand_final: array|null}
     *         chaque nœud est ['match' => objet match, 'team_a' => nœud|null, 'team_b' => nœud|null]
     */
    public static function build(iterable $matches): array
    {
        $matches = is_array($matches) ? $matches : iterator_to_array($matches);

        $childrenOf = [];
        foreach ($matches as $match) {
            if (!empty($match->next_match_id) && !empty($match->next_match_slot)) {
                $childrenOf[(int) $match->next_match_id][$match->next_match_slot] = $match;
            }
        }

        $buildNode = function ($match) use (&$buildNode, $childrenOf) {
            $kids = $childrenOf[(int) $match->id] ?? [];
            return [
                'match'  => $match,
                'team_a' => isset($kids['team_a']) ? $buildNode($kids['team_a']) : null,
                'team_b' => isset($kids['team_b']) ? $buildNode($kids['team_b']) : null,
            ];
        };

        // La racine de chaque phase est son match de round_number le plus élevé (sa finale) :
        // les rounds précédents pointent vers elle via next_match_id, jamais l'inverse.
        $roots = ['upper' => null, 'lower' => null, 'grand_final' => null];
        foreach (array_keys($roots) as $phase) {
            $final = null;
            foreach ($matches as $match) {
                if ($match->phase !== $phase) {
                    continue;
                }
                if ($final === null || (int) $match->round_number > (int) $final->round_number) {
                    $final = $match;
                }
            }
            if ($final !== null) {
                $roots[$phase] = $buildNode($final);
            }
        }

        return $roots;
    }

    /**
     * Rend l'arbre complet en HTML : upper bracket et lower bracket empilés dans une
     * colonne, la grande finale dans une seconde colonne à leur droite (les deux tableaux
     * y convergent naturellement).
     */
    public static function renderFull(array $tree): string
    {
        $main = '';
        if ($tree['upper'] !== null) {
            $main .= self::renderPhase($tree['upper'], 'Upper Bracket');
        }
        if ($tree['lower'] !== null) {
            $main .= self::renderPhase($tree['lower'], 'Lower Bracket');
        }

        $finalHtml = $tree['grand_final'] !== null ? self::renderGrandFinal($tree['grand_final']) : '';

        if ($main === '' && $finalHtml === '') {
            return '';
        }

        $html = '<div class="bracket-columns">';
        if ($main !== '') {
            $html .= '<div class="bracket-main">' . $main . '</div>';
        }
        if ($finalHtml !== '') {
            $html .= '<div class="bracket-final-col">' . $finalHtml . '</div>';
        }
        $html .= '</div>';

        return $html;
    }

    /**
     * Rend une phase (upper ou lower) sous forme de colonnes : une colonne par round, les cartes
     * d'un round étant positionnées verticalement pour que chaque carte se retrouve à mi-chemin
     * entre les deux matchs (ou le match unique) qui l'alimentent.
     */
    private static function renderPhase(array $root, string $label): string
    {
        $columns   = [];
        $leafCount = 0;
        self::collect($root, $columns, $leafCount);

        if ($leafCount === 0) {
            return '';
        }

        ksort($columns);
        $numCols = ((int) array_key_last($columns)) + 1;

        $colWidth = 220;
        $colGap   = 48;
        $rowGap   = 22;
        $rowUnit  = 78;

        $columnTemplate = 'grid-template-columns:repeat(' . $numCols . ',' . $colWidth . 'px);grid-column-gap:' . $colGap . 'px;';

        $headers = '';
        $slots   = '';
        foreach ($columns as $col => $entries) {
            $roundName = htmlspecialchars($entries[0]['match']->round_name ?? '');
            $headers .= '<div class="bracket-col-header" style="grid-column:' . ($col + 1) . '">' . $roundName . '</div>';

            foreach ($entries as $entry) {
                $slots .= self::renderMatchSlot($entry, $col === $numCols - 1);
            }
        }

        $html = '<section class="bracket-phase">';
        $html .= '<p class="bracket-phase__label">' . htmlspecialchars($label) . '</p>';
        $html .= '<div class="bracket-phase__headers" style="' . $columnTemplate . '">' . $headers . '</div>';
        $html .= '<div class="bracket-phase__body" style="' . $columnTemplate . 'grid-template-rows:repeat(' . $leafCount . ',' . $rowUnit . 'px);grid-row-gap:' . $rowGap . 'px;">' . $slots . '</div>';
        $html .= '</section>';

        return $html;
    }

    /**
     * Parcourt récursivement un nœud, lui attribue sa colonne (round_number - 1) et sa plage de
     * lignes dans la grille (bornée par ses enfants, ou une ligne dédiée s'il n'en a pas), et
     * empile l'entrée résultante dans $columns.
     *
     * @return array{0: int, 1: int} [rowStart, rowEnd] du nœud
     */
    private static function collect(array $node, array &$columns, int &$leafCount): array
    {
        $children = [];
        if ($node['team_a'] !== null) {
            $children[] = self::collect($node['team_a'], $columns, $leafCount);
        }
        if ($node['team_b'] !== null) {
            $children[] = self::collect($node['team_b'], $columns, $leafCount);
        }

        if ($children === []) {
            $row = $leafCount++;
            $rowStart = $row;
            $rowEnd   = $row + 1;
        } else {
            $rowStart = min(array_column($children, 0));
            $rowEnd   = max(array_column($children, 1));
        }

        $col = max(0, (int) $node['match']->round_number - 1);
        $columns[$col][] = [
            'match'      => $node['match'],
            'node'       => $node,
            'rowStart'   => $rowStart,
            'rowEnd'     => $rowEnd,
            'childCount' => count($children),
        ];

        return [$rowStart, $rowEnd];
    }

    /** Rend une carte de match positionnée dans la grille CSS de sa phase. */
    private static function renderMatchSlot(array $entry, bool $isLastColumn): string
    {
        $match = $entry['match'];
        $node  = $entry['node'];

        $connectorClass = '';
        if ($entry['childCount'] === 2) {
            $connectorClass = ' bracket-slot--pair';
        } elseif ($entry['childCount'] === 1) {
            $connectorClass = ' bracket-slot--single';
        }

        $style = 'grid-column:' . ((int) $match->round_number) . ';grid-row:' . ($entry['rowStart'] + 1) . ' / ' . ($entry['rowEnd'] + 1) . ';';

        $html = '<div class="bracket-slot' . $connectorClass . '" style="' . $style . '">';
        $html .= self::renderBox($match, $node, !$isLastColumn);
        $html .= '</div>';

        return $html;
    }

    private static function renderGrandFinal(array $node): string
    {
        $match = $node['match'];

        $html = '<section class="bracket-phase bracket-phase--final">';
        $html .= '<p class="bracket-phase__label">Grande finale</p>';
        $html .= '<div class="bracket-phase__headers" style="grid-template-columns:220px;">';
        $html .= '<div class="bracket-col-header">' . htmlspecialchars($match->round_name ?? 'Grande finale') . '</div>';
        $html .= '</div>';
        $html .= '<div class="bracket-phase__body" style="grid-template-columns:220px;grid-template-rows:78px;">';
        $html .= '<div class="bracket-slot" style="grid-column:1;grid-row:1;">';
        $html .= self::renderBox($match, $node, false, true);
        $html .= '</div>';
        $html .= '</div>';
        $html .= '</section>';

        return $html;
    }

    private static function renderBox(object $match, array $node, bool $hasNext, bool $isFinal = false): string
    {
        // Cliquable pour saisir/corriger le score uniquement si les deux équipes sont
        // déjà connues (pas un exempt, pas encore qualifiées pour ce tour) : c'est
        // exactement ce qu'accepte MatchController::score(). Réutilise la classe
        // "match-row" et les mêmes data-* que les tableaux de matchs (voir _scoreModal.php).
        $playable = $match->team_a_id !== null && $match->team_b_id !== null;

        $classes = 'bracket-box'
            . ($isFinal ? ' bracket-box--final' : '')
            . ($hasNext ? ' has-next' : '')
            . ($playable ? ' match-row' : '');

        $dataAttrs = '';
        if ($playable) {
            $dataAttrs = ' data-match-id="' . (int) $match->id . '"'
                . ' data-match-number="' . (int) $match->match_number . '"'
                . ' data-team-a-name="' . htmlspecialchars($match->team_a_name ?? 'Équipe A') . '"'
                . ' data-team-b-name="' . htmlspecialchars($match->team_b_name ?? 'Équipe B') . '"'
                . ' data-score-a="' . (int) $match->score_team_a . '"'
                . ' data-score-b="' . (int) $match->score_team_b . '"'
                . ' data-status="' . htmlspecialchars($match->status) . '"';
        }

        $html = '<div class="' . $classes . '"' . $dataAttrs . '>';
        $html .= '<div class="bracket-box__teams">';
        $html .= self::renderSlot($match, 'a', $node['team_a']);
        $html .= self::renderSlot($match, 'b', $node['team_b']);
        $html .= '</div>';
        $html .= '<button type="button" class="bracket-box__info" data-bs-toggle="tooltip" data-bs-placement="top" title="' . self::matchTooltip($match) . '">';
        $html .= '<i class="fa-solid fa-info"></i>';
        $html .= '</button>';
        $html .= '</div>';

        return $html;
    }

    private static function renderSlot(object $match, string $side, ?array $subNode): string
    {
        $teamId   = $match->{'team_' . $side . '_id'};
        $teamName = $match->{'team_' . $side . '_name'};
        $score    = $match->{'score_team_' . $side};

        if ($teamId !== null) {
            $isWinner = $match->winner_team_id !== null && (int) $match->winner_team_id === (int) $teamId;
            $scoreHtml = in_array($match->status, ['live', 'finished'], true)
                ? '<span class="bracket-box__score">' . (int) $score . '</span>'
                : '';

            return '<div class="bracket-box__team' . ($isWinner ? ' is-winner' : '') . '"><span>'
                . htmlspecialchars($teamName ?? '?') . '</span>' . $scoreHtml . '</div>';
        }

        // Pas d'équipe : soit on attend le résultat du match précédent, soit c'est un exempt (bye).
        $label = $subNode !== null ? 'En attente' : 'Exempt';
        return '<div class="bracket-box__team is-pending"><span>' . $label . '</span></div>';
    }

    /** Construit le texte affiché dans l'info-bulle (numéro de match, terrain, horaire, statut). */
    private static function matchTooltip(object $match): string
    {
        $statusLabels = ['pending' => 'À venir', 'live' => 'En cours', 'finished' => 'Terminé'];

        $parts   = [];
        $parts[] = 'Match #' . (int) $match->match_number;
        if (!empty($match->court_name)) {
            $parts[] = $match->court_name;
        }
        if (!empty($match->scheduled_start)) {
            $timestamp = strtotime($match->scheduled_start);
            if ($timestamp !== false) {
                $parts[] = date('d/m H:i', $timestamp);
            }
        }
        $parts[] = $statusLabels[$match->status] ?? $match->status;

        return htmlspecialchars(implode(' · ', $parts));
    }
}
