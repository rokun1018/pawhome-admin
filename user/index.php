<?php
// Public dashboard: every pet that is available for adoption
require __DIR__ . '/user-common.php';
$adopter = current_adopter();

$rows = $pdo->query("SELECT " . PUBLIC_PET_COLUMNS . " FROM pets p LEFT JOIN shelters s ON s.id = p.shelter_id
                     WHERE p.status = 'available' ORDER BY p.intake_date DESC, p.id DESC")->fetchAll();
$pets = array_map('pet_for_js', $rows);

// Filter choices come from the pets that are actually listed
$breeds = array_unique(array_filter(array_map(fn($p) => $p['breed'], $pets)));
sort($breeds);
$traits = [];
foreach ($pets as $p) foreach ($p['traits'] as $t) $traits[mb_strtolower($t)] = $t;
ksort($traits);
$species = array_unique(array_map(fn($p) => $p['species'], $pets));

user_head('Pet Adoption Dashboard', ['index.css']);
?>
<body>
    <?php user_header('index.php'); ?>
    <?= user_flash_html() ?>

    <!-- Main Content -->
    <main class="main-container">
        <!-- Left Sidebar -->
        <aside class="sidebar">
            <div class="match-card">
                <?php if ($adopter && $adopter['quiz_taken_at']): ?>
                    <h2>Your Matches Are Ready 🐾</h2>
                    <p>Your eligibility quiz score is <strong><?= (int)$adopter['quiz_score'] ?>%</strong>. See the pets that suit your lifestyle best.</p>
                    <button class="btn-primary" type="button" onclick="goToPage('quiz-result.php')">See My Matches</button>
                    <p style="margin-top:.75rem"><a href="quiz.php">Retake the quiz</a></p>
                <?php else: ?>
                    <h2>Find Your Perfect Match 🐾</h2>
                    <p>Unsure which furry friend fits your lifestyle? Take our quick 2-minute personality matching quiz!</p>
                    <button class="btn-primary" type="button" onclick="goToPage('quiz.php')">Start Match Quiz</button>
                <?php endif; ?>
            </div>
        </aside>

        <!-- Right Content -->
        <section class="content">
            <!-- Search and Filters -->
            <div class="search-section">
                <div class="search-bar">
                    <input type="text" id="searchInput" placeholder="Search by name, breed, or character..." class="search-input" aria-label="Search pets">
                    <button class="btn-search" type="button">Search</button>
                </div>

                <div class="filters">
                    <select id="speciesFilter" class="filter-select" aria-label="Species">
                        <option value="">Species: All</option>
                        <?php foreach (['dog' => 'Dog', 'cat' => 'Cat', 'rabbit' => 'Rabbit', 'bird' => 'Bird', 'other' => 'Other'] as $k => $v): if (in_array($k, $species, true)): ?>
                            <option value="<?= $k ?>"><?= $v ?></option>
                        <?php endif; endforeach; ?>
                    </select>

                    <select id="breedFilter" class="filter-select" aria-label="Breed">
                        <option value="">Breed: Any</option>
                        <?php foreach ($breeds as $b): ?><option value="<?= e($b) ?>"><?= e($b) ?></option><?php endforeach; ?>
                    </select>

                    <select id="ageFilter" class="filter-select" aria-label="Age">
                        <option value="">Age: Any</option>
                        <option value="young">Young (0-2 years)</option>
                        <option value="adult">Adult (2-7 years)</option>
                        <option value="senior">Senior (7+ years)</option>
                    </select>

                    <select id="sizeFilter" class="filter-select" aria-label="Size">
                        <option value="">Size: Any</option>
                        <option value="small">Small</option>
                        <option value="medium">Medium</option>
                        <option value="large">Large</option>
                    </select>

                    <select id="traitFilter" class="filter-select" aria-label="Trait">
                        <option value="">Trait: Any</option>
                        <?php foreach ($traits as $t): ?><option value="<?= e($t) ?>"><?= e($t) ?></option><?php endforeach; ?>
                    </select>
                </div>
            </div>

            <!-- Pet Cards Grid -->
            <div class="pets-grid" id="petsGrid">
                <!-- Pet cards are filled in by js/index.js -->
            </div>
        </section>
    </main>

    <script>const petsData = <?= json_encode($pets, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;</script>
    <?php user_scripts(['index.js']); ?>
</body>
</html>
