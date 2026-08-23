<?php if (!defined('APP_ROOT')) exit; ?>
<script src="https://cdn.jsdelivr.net/npm/apexcharts"></script>

<style>
    .charts-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(460px, 1fr)); gap: 20px; margin-top: 20px; }
    @media (max-width: 600px) { .charts-grid { grid-template-columns: 1fr; } }
    .chart-card { background: #fff; padding: 20px; border: 1px solid var(--border-color); border-radius: 8px; box-shadow: 0 2px 5px rgba(0,0,0,0.02); }
    .badge-bar { background: #e8f8f5; color: #16a085; padding: 4px 8px; border-radius: 4px; font-size: 0.8em; font-weight: bold; }
    .badge-area { background: #ebf5fb; color: #2980b9; padding: 4px 8px; border-radius: 4px; font-size: 0.8em; font-weight: bold; }
</style>

<div class="card card-primary-top">
    <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px;">
        <h3 style="margin: 0;">
            <span class="material-symbols-outlined" style="vertical-align: middle; color: #e67e22;">monitoring</span> 
            Analytika provozu: <?= htmlspecialchars($asset['name']) ?>
        </h3>
        <a href="index.php?page=assets" class="btn" style="background: #95a5a6; text-decoration: none;">
            <span class="material-symbols-outlined" style="vertical-align: middle;">arrow_back</span> Zpět na zařízení
        </a>
    </div>
    
    <?php if (empty($chartData)): ?>
        <div style="padding: 25px; background: #fff3cd; color: #856404; border-radius: 6px; border: 1px solid #ffeeba; margin-top: 20px; text-align: center;">
            <span class="material-symbols-outlined" style="font-size: 3em; margin-bottom: 10px;">hourglass_empty</span><br>
            <strong>Zatím není k dispozici dostatek číselných dat.</strong><br>
            Grafy se začnou automaticky vykreslovat po odeslání záznamů.
        </div>
    <?php else: ?>
        <div class="charts-grid">
            <?php $chartIndex = 0; foreach ($chartData as $key => $c): $chartIndex++; ?>
                <div class="chart-card">
                    <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 15px;">
                        <h4 style="margin: 0; color: #2c3e50; font-size: 1.1em;">
                            <?= htmlspecialchars($c['title']) ?>
                        </h4>
                        <?php if ($c['type'] === 'bar'): ?>
                            <span class="badge-bar"><span class="material-symbols-outlined" style="font-size: 1.1em; vertical-align: middle;">bar_chart</span> Spotřeba / Čítač</span>
                        <?php else: ?>
                            <span class="badge-area"><span class="material-symbols-outlined" style="font-size: 1.1em; vertical-align: middle;">show_chart</span> Průběh</span>
                        <?php endif; ?>
                    </div>
                    
                    <div id="chart-<?= $chartIndex ?>"></div>
                </div>
            <?php endforeach; ?>
        </div>

        <script>
            document.addEventListener("DOMContentLoaded", function() {
                <?php $chartIndex = 0; foreach ($chartData as $key => $c): $chartIndex++; ?>
                    var isBar = <?= json_encode($c['type'] === 'bar') ?>;
                    
                    var options<?= $chartIndex ?> = {
                        series: [{
                            name: '<?= htmlspecialchars($key) ?>',
                            data: <?= json_encode($c['points']) ?>
                        }],
                        chart: {
                            type: isBar ? 'bar' : 'area',
                            height: 300,
                            toolbar: { show: false },
                            zoom: { enabled: false }
                        },
                        colors: isBar ? ['#16a085'] : ['#2980b9'],
                        plotOptions: {
                            bar: {
                                borderRadius: 5,
                                columnWidth: '45%',
                                dataLabels: { position: 'top' }
                            }
                        },
                        stroke: {
                            curve: 'straight', // Narovnání čáry namísto hladké vlnovky
                            width: isBar ? 0 : 3
                        },
                        dataLabels: {
                            enabled: true,
                            formatter: function (val) {
                                return (isBar ? '+' : '') + val;
                            },
                            offsetY: isBar ? -20 : 0,
                            style: {
                                fontSize: '11px',
                                colors: isBar ? ['#16a085'] : ['#333']
                            },
                            background: { enabled: !isBar, borderRadius: 4, borderWidth: 0 }
                        },
                        xaxis: {
                            type: 'datetime', // Použití reálného času pro propojení bodů
                            labels: { 
                                datetimeUTC: false, 
                                format: 'dd.MM. HH:mm',
                                style: { fontSize: '11px', colors: '#777' } 
                            }
                        },
                        yaxis: {
                            labels: { style: { colors: '#777', fontWeight: 'bold' } }
                        },
                        fill: {
                            type: isBar ? 'solid' : 'gradient',
                            gradient: {
                                shadeIntensity: 1,
                                opacityFrom: 0.6,
                                opacityTo: 0.1,
                                stops: [0, 90, 100]
                            }
                        },
                        tooltip: {
                            x: { format: 'dd.MM.yyyy HH:mm' },
                            y: {
                                formatter: function(val, opts) {
                                    if (isBar) {
                                        var point = opts.w.config.series[opts.seriesIndex].data[opts.dataPointIndex];
                                        var totalStr = point.total !== undefined ? ' (Celkový stav: ' + point.total + ')' : '';
                                        return '+' + val + totalStr;
                                    }
                                    return val;
                                }
                            }
                        },
                        markers: { size: isBar ? 0 : 4, colors: ['#fff'], strokeColors: '#2980b9', strokeWidth: 2 }
                    };

                    var chart<?= $chartIndex ?> = new ApexCharts(document.querySelector("#chart-<?= $chartIndex ?>"), options<?= $chartIndex ?>);
                    chart<?= $chartIndex ?>.render();
                <?php endforeach; ?>
            });
        </script>
    <?php endif; ?>
</div>
