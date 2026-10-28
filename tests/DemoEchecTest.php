<?php

/**
 * DÉMONSTRATION CI : test volontairement faux pour montrer qu'un test en échec
 * bloque la fusion de la Pull Request et donc le déploiement. À supprimer.
 */

use tournoi_enoa\Tests\MiniTest;

MiniTest::suite('Démo CI — test volontairement en échec');

MiniTest::test('1 + 1 = 3 (faux exprès)', function () {
    MiniTest::assertSame(3, 1 + 1, 'démo du blocage CI');
});
