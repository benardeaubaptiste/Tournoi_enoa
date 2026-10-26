<?php
/**
 * Contenu de la section "Équipes" (tableau + joueurs), réutilisé par pdf/teams.php
 * (document seul) et pdf/full.php (bilan complet).
 * @var \tournoi_enoa\Entities\Team[] $teams
 * @var array<int,\tournoi_enoa\Entities\Player[]> $playersByTeam
 */
$paiementLabels = ['paye' => 'Payé', 'partiel' => 'Partiel', 'impaye' => 'Non payé'];
$paiementBadges = ['paye' => 'badge-success', 'partiel' => 'badge-warning', 'impaye' => 'badge-danger'];
$statutLabels = ['inscrite' => 'Inscrite', 'confirmee' => 'Confirmée', 'forfait' => 'Forfait', 'elimine' => 'Éliminée'];
?>
<h2>Équipes</h2>

<?php if (!$teams): ?>
    <p class="muted">Aucune équipe inscrite.</p>
<?php else: ?>
    <table>
        <thead>
            <tr>
                <th>#</th><th>Équipe</th><th>Club</th><th class="text-center">Joueurs</th><th>Statut</th>
                <th class="text-right">À payer</th><th class="text-right">Payé</th><th>Paiement</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($teams as $team): ?>
                <?php $paiement = $team->statutPaiementReel(); ?>
                <tr>
                    <td><?= (int) $team->numero_inscription ?></td>
                    <td><strong><?= htmlspecialchars($team->nom) ?></strong></td>
                    <td><?= htmlspecialchars($team->club ?? '—') ?></td>
                    <td class="text-center"><?= (int) $team->nombre_joueurs ?></td>
                    <td><?= htmlspecialchars($statutLabels[$team->statut] ?? $team->statut) ?></td>
                    <td class="text-right"><?= number_format((float) $team->montant_a_payer, 2, ',', ' ') ?> €</td>
                    <td class="text-right"><?= number_format((float) $team->montant_paye, 2, ',', ' ') ?> €</td>
                    <td><span class="badge <?= $paiementBadges[$paiement] ?? 'badge-secondary' ?>"><?= $paiementLabels[$paiement] ?? $paiement ?></span></td>
                </tr>
                <?php if ($playersByTeam[$team->id] ?? []): ?>
                    <tr>
                        <td></td>
                        <td colspan="7" class="muted">
                            <?php
                            $noms = array_map(
                                static function ($player) {
                                    $full = trim($player->prenom . ' ' . $player->nom);
                                    return $player->numero_maillot ? $full . ' (n°' . $player->numero_maillot . ')' : $full;
                                },
                                $playersByTeam[$team->id]
                            );
                            echo htmlspecialchars(implode(' · ', $noms));
                            ?>
                        </td>
                    </tr>
                <?php endif; ?>
            <?php endforeach; ?>
        </tbody>
    </table>
<?php endif; ?>
