<?php if (!defined('APP_ROOT')) exit; ?>
<!-- Načtení knihovny pro vykreslování grafů -->
<script src="https://cdn.jsdelivr.net/npm/apexcharts"></script>

<div class="card card-primary-top">
    <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px;">
        <h3 style="margin: 0; color: var(--text-main);">
            <span class="material-symbols-outlined" style="vertical-align: middle; color: var(--warning);">monitoring</span> 
            Analytika provozu: <?= htmlspecialchars($asset['name']) ?>
        </h3>
        <a href="index.php?page=assets" class="btn" style="background: var(--text-muted); text-decoration: none;">
            <span class="material-symbols-outlined" style="vertical-align: middle;">arrow_back</span> Zpět na zařízení
        </a>
    </div>
    
    <?php if (empty($chartData)): ?>
        <div style="padding: 25px; background: rgba(243, 156, 18, 0.15); color: var(--warning); border-radius: 6px; border: 1px solid var(--warning); margin-top: 20px; text-align: center;">
            <span class="material-symbols-outlined" style="font-size: 3em; margin-bottom: 10px;">hourglass_empty</span><br>
            <strong>Zatím není k dispozici dostatek číselných dat.</strong><br>
            Grafy se začnou automaticky vykreslovat po odeslání záznamů.
        </div>
    <?php else: ?>
        <!-- 
            Zde posíláme PHP pole $chartData jako JSON atribut, 
            aby si jej mohl přečíst náš globální JavaScript (app.js) 
        -->
        <div class="charts-grid" id="analytics-charts-container" data-charts="<?= htmlspecialchars(json_encode($chartData, JSON_HEX_APOS | JSON_HEX_QUOT)) ?>">
            <?php $chartIndex = 0; foreach ($chartData as $key => $c): $chartIndex++; ?>
                <div class="chart-card">
                    <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 15px;">
                        <h4 style="margin: 0; color: var(--text-main); font-size: 1.1em;">
                            <?= htmlspecialchars($c['title']) ?>
                        </h4>
                        <?php if ($c['type'] === 'bar'): ?>
                            <span class="badge-bar"><span class="material-symbols-outlined" style="font-size: 1.1em; vertical-align: middle;">bar_chart</span> Spotřeba / Čítač</span>
                        <?php else: ?>
                            <span class="badge-area"><span class="material-symbols-outlined" style="font-size: 1.1em; vertical-align: middle;">show_chart</span> Průběh</span>
                        <?php endif; ?>
                    </div>
                    
                    <!-- Prázdný div, do kterého JS (app.js) vykreslí graf -->
                    <div id="chart-<?= $chartIndex ?>"></div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>
