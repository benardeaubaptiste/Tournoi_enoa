'use strict';

/**
 * Test fonctionnel du minuteur de match (Views/_scheduleWidget.php) : on exécute LE VRAI
 * script tel qu'il est servi au navigateur (extrait du fichier PHP, pas une réimplémentation
 * à côté), dans un DOM minimal simulé où setInterval/clearInterval sont sous notre contrôle
 * (un "tick()" manuel avance le temps d'une seconde sans attendre de vraies secondes).
 *
 * Depuis l'ajout d'un bouton par étape du tournoi (qualification / phase finale / grande
 * finale, chacun avec sa propre durée), le DOM simulé expose ".js-timer-mode" via un faux
 * querySelectorAll plutôt que des ids fixes — voir buildTimerHarness().
 *
 * Lancer : node tests/timer.test.js
 * Nécessite Node.js (aucune dépendance npm).
 */

const fs = require('fs');
const path = require('path');
const vm = require('vm');
const assert = require('assert');

const widgetPath = path.join(__dirname, '..', 'Views', '_scheduleWidget.php');
const source = fs.readFileSync(widgetPath, 'utf8');

const scriptMatch = source.match(/<script>([\s\S]*?)<\/script>/);
if (!scriptMatch) {
    console.error('FAIL: impossible de trouver le <script> du minuteur dans _scheduleWidget.php (le fichier a-t-il changé de structure ?)');
    process.exit(1);
}
const timerScript = scriptMatch[1];

let passed = 0;
let failed = 0;
const failures = [];

function test(name, fn) {
    try {
        fn();
        passed++;
        console.log(`  [OK]   ${name}`);
    } catch (e) {
        failed++;
        failures.push({ name, message: e.message });
        console.log(`  [FAIL] ${name}`);
        console.log(`         -> ${e.message}`);
    }
}

/** classList minimal (add/remove/contains/toggle) pour les boutons de bascule de mode. */
function makeButtonClassList(initialClasses) {
    const set = new Set(initialClasses);
    return {
        add: (c) => set.add(c),
        remove: (c) => set.delete(c),
        contains: (c) => set.has(c),
        toggle: (c, force) => {
            const on = force !== undefined ? force : !set.has(c);
            if (on) set.add(c); else set.delete(c);
            return on;
        },
    };
}

/**
 * Construit un DOM minimal + horloge manuelle, exécute le script du minuteur dedans.
 *
 * @param {Array<{key:string, seconds:number, hint?:string, active?:boolean, disabled?:boolean}>} stages
 *        un bouton par étape (qualification / phase_finale / grande_finale...) ; une et une
 *        seule doit porter active:true (celle pré-sélectionnée par le PHP au chargement, comme
 *        $defaultStage dans _scheduleWidget.php).
 */
function buildTimerHarness(stages) {
    const activeStage = stages.find((s) => s.active);
    if (!activeStage) {
        throw new Error('buildTimerHarness: une des étapes doit être "active" (celle sélectionnée par défaut au chargement)');
    }

    const listeners = { toggle: [], reset: [] };
    const modeListeners = new Map(); // bouton -> [handlers]
    let intervalCallback = null;
    let intervalActive = false;

    const display = { textContent: '', classList: { toggled: {}, toggle(cls, on) { this.toggled[cls] = !!on; } } };
    const hint = { textContent: '' };
    const toggleBtn = {
        textContent: '',
        disabled: false,
        addEventListener(evt, fn) { if (evt === 'click') listeners.toggle.push(fn); },
    };
    const resetBtn = {
        addEventListener(evt, fn) { if (evt === 'click') listeners.reset.push(fn); },
    };
    const timerEl = { dataset: { durationSeconds: String(activeStage.seconds) } };
    const endModalEl = {};

    const modeButtons = stages.map((stage) => {
        const btn = {
            key: stage.key,
            dataset: { durationSeconds: String(stage.seconds), durationHint: stage.hint || '' },
            disabled: !!stage.disabled,
            classList: makeButtonClassList(stage.active ? ['btn', 'btn-sm', 'btn-enoa', 'active'] : ['btn', 'btn-sm', 'btn-outline-light']),
            addEventListener(evt, fn) {
                if (evt !== 'click') return;
                if (!modeListeners.has(btn)) modeListeners.set(btn, []);
                modeListeners.get(btn).push(fn);
            },
        };
        return btn;
    });

    const elements = {
        qualTimer: timerEl,
        qualTimerDisplay: display,
        qualTimerHint: hint,
        qualTimerToggle: toggleBtn,
        qualTimerReset: resetBtn,
        matchEndModal: endModalEl,
    };

    const fakeDocument = {
        readyState: 'complete',
        getElementById: (id) => elements[id] || null,
        querySelectorAll: (selector) => (selector === '.js-timer-mode' ? modeButtons : []),
        addEventListener() { /* readyState is 'complete', jamais appelé */ },
    };

    // new bootstrap.Modal(el) : le vrai script en crée une seule instance à l'initialisation
    // et la réutilise à chaque fin de match (Bootstrap 5.0.0-beta3 n'a pas getOrCreateInstance,
    // apparu en 5.2 — voir _scheduleWidget.php) ; on espionne .show() sur cette instance.
    let modalShowCount = 0;
    const fakeBootstrap = { Modal: function () { this.show = () => { modalShowCount++; }; } };

    const sandbox = {
        document: fakeDocument,
        bootstrap: fakeBootstrap,
        setInterval: (fn) => { intervalCallback = fn; intervalActive = true; return 1; },
        clearInterval: () => { intervalActive = false; intervalCallback = null; },
        console,
    };
    vm.createContext(sandbox);
    vm.runInContext(timerScript, sandbox);

    function buttonByKey(key) {
        const btn = modeButtons.find((b) => b.key === key);
        if (!btn) {
            throw new Error(`aucun bouton de minuteur pour l'étape "${key}"`);
        }
        return btn;
    }

    return {
        display,
        hint,
        toggleBtn,
        resetBtn,
        button: buttonByKey,
        clickToggle: () => listeners.toggle.forEach((fn) => fn()),
        clickReset: () => listeners.reset.forEach((fn) => fn()),
        clickMode: (key) => {
            const btn = buttonByKey(key);
            (modeListeners.get(btn) || []).forEach((fn) => fn());
        },
        tick: (seconds = 1) => {
            for (let i = 0; i < seconds; i++) {
                if (!intervalActive || !intervalCallback) break;
                intervalCallback();
            }
        },
        isRunning: () => intervalActive,
        modalShowCount: () => modalShowCount,
    };
}

/** Un seul bouton actif — pour les tests qui ne portent pas sur la bascule entre étapes. */
function singleStage(seconds) {
    return [{ key: 'x', seconds, hint: '', active: true }];
}

console.log('=== Minuteur de match (_scheduleWidget.php) — exécution du vrai script servi au navigateur ===');

test('affichage initial : MM:SS calculé à partir de data-duration-seconds (5 min -> 05:00)', () => {
    const h = buildTimerHarness(singleStage(5 * 60));
    assert.strictEqual(h.display.textContent, '05:00');
});

test('affichage initial : durée non multiple de 60s (90s -> 01:30)', () => {
    const h = buildTimerHarness(singleStage(90));
    assert.strictEqual(h.display.textContent, '01:30');
});

test('Démarrer puis 60 ticks : le minuteur décompte bien à raison d\'1 seconde par tick', () => {
    const h = buildTimerHarness(singleStage(5 * 60));
    h.clickToggle(); // Démarrer
    h.tick(60);
    assert.strictEqual(h.display.textContent, '04:00');
});

test('Pause : le décompte s\'arrête (les ticks suivants n\'ont plus d\'effet)', () => {
    const h = buildTimerHarness(singleStage(5 * 60));
    h.clickToggle(); // Démarrer
    h.tick(10);
    h.clickToggle(); // Pause
    assert.strictEqual(h.isRunning(), false, 'le minuteur ne doit plus tourner après pause');
    h.tick(30); // ne doit rien changer, pas d'interval actif
    assert.strictEqual(h.display.textContent, '04:50');
});

test('Reprise après pause : repart bien du temps restant, pas du total', () => {
    const h = buildTimerHarness(singleStage(5 * 60));
    h.clickToggle(); // Démarrer
    h.tick(10);
    h.clickToggle(); // Pause (4:50 restant)
    h.clickToggle(); // Reprendre
    h.tick(50);
    assert.strictEqual(h.display.textContent, '04:00');
});

test('Réinitialiser : revient au total initial et réactive le bouton Démarrer', () => {
    const h = buildTimerHarness(singleStage(5 * 60));
    h.clickToggle();
    h.tick(200);
    h.clickReset();
    assert.strictEqual(h.display.textContent, '05:00');
    assert.strictEqual(h.toggleBtn.disabled, false);
});

test('Fin du temps : s\'arrête pile à 00:00 (jamais de négatif), passe en état "terminé"', () => {
    const h = buildTimerHarness(singleStage(3)); // 3 secondes pour un test rapide
    h.clickToggle();
    h.tick(10); // beaucoup plus que nécessaire : ne doit jamais passer sous 0
    assert.strictEqual(h.display.textContent, '00:00');
    assert.strictEqual(h.display.classList.toggled['schedule-widget__timer-display--done'], true);
    assert.strictEqual(h.isRunning(), false, 'le minuteur doit s\'auto-arrêter à 0, pas continuer à tourner dans le vide');
});

test('À 00:00, le bouton Démarrer est désactivé (rien à relancer sans Réinitialiser)', () => {
    const h = buildTimerHarness(singleStage(1));
    h.clickToggle();
    h.tick(5);
    assert.strictEqual(h.toggleBtn.disabled, true);
});

test('Double clic rapide sur le même bouton (Démarrer puis Pause) ne pose pas un 2e intervalle qui accélérerait le décompte', () => {
    // Le bouton fait office de Démarrer/Pause : le handler re-teste `interval` à chaque
    // clic, donc un 2e clic juste après le 1er bascule en pause au lieu d'armer un 2e
    // setInterval (ce qui ferait décompter 2 secondes par tick au lieu d'une).
    const h = buildTimerHarness(singleStage(60));
    h.clickToggle(); // Démarrer
    h.clickToggle(); // Pause (aucun tick entre les deux clics : toujours 01:00)
    assert.strictEqual(h.isRunning(), false, 'le 2e clic doit mettre en pause, pas relancer un 2e intervalle');
    h.tick(10); // ne doit rien changer : plus d'intervalle actif
    assert.strictEqual(h.display.textContent, '01:00', 'un double-clic ne doit pas faire décompter le temps pendant qu\'il est en pause');
});

test('Popup "fin de match" : s\'affiche exactement quand le décompte atteint 00:00', () => {
    const h = buildTimerHarness(singleStage(3));
    h.clickToggle();
    h.tick(2);
    assert.strictEqual(h.modalShowCount(), 0, 'ne doit pas encore s\'afficher avant la fin (00:01 restant)');
    h.tick(1); // atteint 0
    assert.strictEqual(h.modalShowCount(), 1, 'doit s\'afficher exactement une fois à 00:00');
});

test('Popup "fin de match" : ne s\'affiche pas tant que le minuteur tourne encore ou est juste en pause', () => {
    const h = buildTimerHarness(singleStage(60));
    h.clickToggle(); // Démarrer
    h.tick(30);
    h.clickToggle(); // Pause à mi-parcours (jamais atteint 0)
    assert.strictEqual(h.modalShowCount(), 0);
});

test('Popup "fin de match" : ne se déclenche plus une 2e fois si le temps reste à 0 (pas de tick fantôme)', () => {
    const h = buildTimerHarness(singleStage(2));
    h.clickToggle();
    h.tick(10); // beaucoup plus que nécessaire, mais l'intervalle s'auto-arrête à 0
    assert.strictEqual(h.modalShowCount(), 1, 'un seul déclenchement, même avec des ticks en trop après la fin');
});

test('Popup "fin de match" : réapparaît si on réinitialise puis relance le minuteur jusqu\'au bout', () => {
    const h = buildTimerHarness(singleStage(2));
    h.clickToggle();
    h.tick(2);
    assert.strictEqual(h.modalShowCount(), 1);
    h.clickReset();
    h.clickToggle();
    h.tick(2);
    assert.strictEqual(h.modalShowCount(), 2, 'un nouveau match chronométré doit redéclencher le popup à sa propre fin');
});

console.log('\n=== Un bouton par étape (qualification / phase finale / grande finale) ===');

/** Les trois étapes du tournoi, chacune avec sa propre durée — le scénario réel de la page. */
function tournamentStages(activeKey, overrides = {}) {
    const seconds = { qualification: 21 * 60, phase_finale: 22 * 60, grande_finale: 20 * 60, ...overrides };
    return ['qualification', 'phase_finale', 'grande_finale'].map((key) => ({
        key,
        seconds: seconds[key],
        hint: 'hint:' + key,
        active: key === activeKey,
    }));
}

test('BUG signalé : qualification, phase finale et grande finale ont bien 3 durées différentes, jamais interchangées par défaut', () => {
    // Reproduit le signalement d'origine (la grande finale restait chronométrée à la durée
    // d'un match de phase finale, 17 min, au lieu de ses 20 min fixes) sur les 3 étapes à la fois.
    const h = buildTimerHarness(tournamentStages('phase_finale', { phase_finale: 17 * 60 }));
    assert.strictEqual(h.display.textContent, '17:00', 'la page de l\'arbre affiche par défaut la durée de phase finale, pas celle d\'une autre étape');
});

test('Bouton "Grande finale" : bascule sur sa durée fixe, quelle que soit l\'étape active au départ', () => {
    const h = buildTimerHarness(tournamentStages('phase_finale', { phase_finale: 17 * 60 }));
    h.clickMode('grande_finale');
    assert.strictEqual(h.display.textContent, '20:00');
    assert.strictEqual(h.button('grande_finale').classList.contains('active'), true);
    assert.strictEqual(h.button('phase_finale').classList.contains('active'), false, 'un seul mode actif à la fois');
    assert.strictEqual(h.button('qualification').classList.contains('active'), false);
});

test('Bouton "Qualification" : accessible même depuis la page de l\'arbre (pas seulement phase finale/grande finale)', () => {
    const h = buildTimerHarness(tournamentStages('phase_finale'));
    h.clickMode('qualification');
    assert.strictEqual(h.display.textContent, '21:00');
    assert.strictEqual(h.button('qualification').classList.contains('active'), true);
});

test('Revenir à l\'étape initiale après être passé par les deux autres', () => {
    const h = buildTimerHarness(tournamentStages('qualification'));
    h.clickMode('grande_finale');
    h.clickMode('phase_finale');
    h.clickMode('qualification');
    assert.strictEqual(h.display.textContent, '21:00');
    assert.strictEqual(h.button('qualification').classList.contains('active'), true);
    assert.strictEqual(h.button('phase_finale').classList.contains('active'), false);
    assert.strictEqual(h.button('grande_finale').classList.contains('active'), false);
});

test('Changer d\'étape mémorise le vrai total : Réinitialiser revient à la durée de LA NOUVELLE étape, pas de l\'ancienne', () => {
    const h = buildTimerHarness(tournamentStages('phase_finale', { phase_finale: 17 * 60 }));
    h.clickMode('grande_finale');
    h.clickToggle(); // Démarrer
    h.tick(5 * 60); // 5 min décomptées
    assert.strictEqual(h.display.textContent, '15:00');
    h.clickReset();
    assert.strictEqual(h.display.textContent, '20:00', 'Réinitialiser doit revenir aux 20 min de la grande finale, pas aux 17 min de phase finale');
});

test('Changer d\'étape remet le minuteur en pause (pas de décompte fantôme sur l\'ancienne durée)', () => {
    const h = buildTimerHarness(tournamentStages('qualification'));
    h.clickToggle(); // Démarrer en qualification
    h.tick(60);
    h.clickMode('grande_finale');
    assert.strictEqual(h.isRunning(), false, 'changer d\'étape doit arrêter le décompte en cours');
    assert.strictEqual(h.display.textContent, '20:00', 'et repartir du total de la nouvelle étape, pas du restant de l\'ancienne');
});

test('Le texte d\'aide (hint) suit l\'étape sélectionnée', () => {
    const h = buildTimerHarness(tournamentStages('qualification'));
    h.clickMode('grande_finale');
    assert.strictEqual(h.hint.textContent, 'hint:grande_finale');
    h.clickMode('phase_finale');
    assert.strictEqual(h.hint.textContent, 'hint:phase_finale');
});

test('Étape pas encore calculable (réglages incomplets) : le bouton est désactivé et ne réagit pas au clic', () => {
    const stages = tournamentStages('grande_finale');
    stages.find((s) => s.key === 'qualification').disabled = true;
    stages.find((s) => s.key === 'qualification').seconds = 0;
    const h = buildTimerHarness(stages);

    h.clickMode('qualification'); // ne doit rien faire : le vrai script n'attache aucun listener sur un bouton disabled
    assert.strictEqual(h.display.textContent, '20:00', 'le clic sur un bouton désactivé ne doit rien changer');
    assert.strictEqual(h.button('grande_finale').classList.contains('active'), true, 'l\'étape active ne doit pas changer');
});

console.log(`\n=== Résumé ===\n${passed + failed} test(s), ${passed} OK, ${failed} échec(s)`);
if (failures.length) {
    console.log('\nDétail des échecs :');
    failures.forEach((f) => console.log(` - ${f.name} : ${f.message}`));
}
process.exit(failed > 0 ? 1 : 0);
