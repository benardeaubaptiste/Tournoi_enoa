<?php

namespace tournoi_enoa\Core;

/**
 * Calcule la durée des matchs à partir de l'heure de début/fin de chaque phase : le
 * temps de battement entre matchs reste le seul réglage fixé par l'utilisateur, tout le
 * reste (nombre de créneaux, durée d'un match) se déduit de la fenêtre disponible et du
 * nombre de matchs à y caser. Aucune donnée n'est lue ni écrite en base ici.
 */
class ScheduleCalculator
{
    /** Valeur de repli pour la durée de la grande finale, tant qu'aucun réglage n'existe encore pour le tournoi (voir tournament_settings.duree_grande_finale, réglable). */
    public const GRAND_FINAL_DURATION_MINUTES = 20;

    /**
     * Nombre de matchs de qualification (poules équilibrées, un tour aller simple)
     * pour un effectif et un nombre de poules donnés.
     */
    public static function qualificationMatchCount(int $nombreMaxEquipes, int $nombrePoules): int
    {
        if ($nombrePoules <= 0 || $nombreMaxEquipes <= 1) {
            return 0;
        }

        $base = intdiv($nombreMaxEquipes, $nombrePoules);
        $reste = $nombreMaxEquipes % $nombrePoules;

        $total = 0;
        for ($i = 0; $i < $nombrePoules; $i++) {
            $taille = $base + ($i < $reste ? 1 : 0);
            $total += self::roundRobinMatchCount($taille);
        }
        return $total;
    }

    /**
     * Matchs de phase finale (upper bracket + lower bracket, élimination directe avec
     * exempts), grande finale à part puisqu'elle a une durée fixe.
     *
     * @return array{bracket:int, grande_finale:bool}
     */
    public static function finalPhaseMatchCount(int $nombreEquipesUpper, int $nombreEquipesLower): array
    {
        $bracket = self::singleEliminationMatchCount($nombreEquipesUpper)
            + self::singleEliminationMatchCount($nombreEquipesLower);

        return [
            'bracket'       => $bracket,
            'grande_finale' => $nombreEquipesUpper > 0 && $nombreEquipesLower > 0,
        ];
    }

    /**
     * Minutes entre deux heures "HH:MM" ou "HH:MM:SS" (0 si la fenêtre est absente ou
     * invalide). Une fin inférieure ou égale au début est interprétée comme un tournoi
     * qui se termine après minuit (ex. 20:00 -> 00:00 = 4h), pas comme une erreur.
     */
    public static function windowMinutes(?string $heureDebut, ?string $heureFin): int
    {
        if (!$heureDebut || !$heureFin) {
            return 0;
        }

        $debut = strtotime($heureDebut);
        $fin = strtotime($heureFin);
        if ($debut === false || $fin === false) {
            return 0;
        }
        if ($fin <= $debut) {
            $fin += 86400; // la fin se produit après minuit, le lendemain du début
        }

        return (int) floor(($fin - $debut) / 60);
    }

    /**
     * Résout la durée de match qui permet de caser $nombreMatchs matchs, séparés par
     * $tempsEntreMatchs minutes de battement, sur $nombreTerrains terrains, dans une
     * fenêtre de $fenetreMinutes minutes (dont on retire, le cas échéant, $reserveMinutes
     * déjà occupées par ailleurs, ex. la grande finale et son propre battement).
     *
     * @return array{creneaux:int, duree_match:int, faisable:bool}
     *         duree_match est arrondi à la minute inférieure ; faisable=false si la
     *         fenêtre est trop courte pour tenir ne serait-ce qu'une minute par match.
     */
    public static function resolveMatchDuration(
        int $fenetreMinutes,
        int $nombreMatchs,
        int $nombreTerrains,
        int $tempsEntreMatchs,
        int $reserveMinutes = 0
    ): array {
        if ($nombreMatchs <= 0) {
            return ['creneaux' => 0, 'duree_match' => 0, 'faisable' => true];
        }

        $nombreTerrains = max(1, $nombreTerrains);
        $disponible = $fenetreMinutes - $reserveMinutes;
        $creneaux = (int) ceil($nombreMatchs / $nombreTerrains);
        $dureeMatch = (int) floor(($disponible - ($creneaux - 1) * $tempsEntreMatchs) / $creneaux);

        return [
            'creneaux'    => $creneaux,
            'duree_match' => max(0, $dureeMatch),
            'faisable'    => $dureeMatch >= 1,
        ];
    }

    /**
     * Répartit une durée totale entre deux phases au prorata de leur nombre de créneaux
     * (le nombre de créneaux reflète le temps nécessaire à chaque phase, terrains pris en
     * compte, indépendamment de la durée de match qui reste à déterminer). Si aucune des
     * deux phases n'a de créneau, tout revient à la première par convention.
     *
     * @return array{qualification:int, phase_finale:int}
     */
    public static function splitWindow(int $totalMinutes, int $creneauxQualification, int $creneauxPhaseFinale): array
    {
        $totalCreneaux = $creneauxQualification + $creneauxPhaseFinale;
        if ($totalCreneaux <= 0) {
            return ['qualification' => $totalMinutes, 'phase_finale' => 0];
        }

        $qualification = (int) round($totalMinutes * $creneauxQualification / $totalCreneaux);
        return ['qualification' => $qualification, 'phase_finale' => $totalMinutes - $qualification];
    }

    private static function roundRobinMatchCount(int $nombreEquipes): int
    {
        return $nombreEquipes > 1 ? intdiv($nombreEquipes * ($nombreEquipes - 1), 2) : 0;
    }

    private static function singleEliminationMatchCount(int $nombreEquipes): int
    {
        return max(0, $nombreEquipes - 1);
    }
}
