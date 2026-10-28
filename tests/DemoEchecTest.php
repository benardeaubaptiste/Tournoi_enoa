<?php

/**
 * DÉMONSTRATION CI : test volontairement faux, pour prouver qu'un test en échec bloque
 * la fusion d'une Pull Request et le déploiement.
 *
 * Il n'est exécuté que si la variable d'environnement DEMO_ECHEC vaut 1 :
 *   - GitHub : Actions → CI/CD → Run workflow → cocher « Simuler un échec » ;
 *   - en local : DEMO_ECHEC=1 php tests/run.php
 * Sans elle (push, PR, exécution normale), il ne fait rien.
 */

use tournoi_enoa\Tests\MiniTest;

if (getenv('DEMO_ECHEC') === '1') {
    MiniTest::suite('Démo CI — test volontairement en échec (DEMO_ECHEC=1)');

    MiniTest::test('1 + 1 = 3 (faux exprès)', function () {
        MiniTest::assertSame(3, 1 + 1, 'démo du blocage CI');
    });
}
