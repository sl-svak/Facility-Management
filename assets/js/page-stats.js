/**
 * Grafy a analytika zařízení (asset_stats.php) - Chart.js
 * Data předává PHP v atributu data-charts kontejneru #analytics-charts-container.
 */
(function () {
    function pad(n) { return n < 10 ? '0' + n : '' + n; }
    function fmtShort(ts) {
        const d = new Date(ts);
        return pad(d.getDate()) + '.' + pad(d.getMonth() + 1) + '. ' + pad(d.getHours()) + ':' + pad(d.getMinutes());
    }
    function fmtFull(ts) {
        const d = new Date(ts);
        return pad(d.getDate()) + '.' + pad(d.getMonth() + 1) + '.' + d.getFullYear() + ' ' + pad(d.getHours()) + ':' + pad(d.getMinutes());
    }

    function init() {
        const container = document.getElementById('analytics-charts-container');
        if (!container || typeof Chart === 'undefined') return;

        let chartData;
        try { chartData = JSON.parse(container.getAttribute('data-charts') || '{}'); } catch (e) { return; }

        const isDark = document.body.classList.contains('theme-dark') ||
            (window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches && !document.body.classList.contains('theme-light'));
        const axisText = isDark ? '#aaaaaa' : '#777777';
        const gridColor = isDark ? 'rgba(255,255,255,0.08)' : 'rgba(0,0,0,0.08)';
        const markerBg = isDark ? '#1e1e1e' : '#ffffff';
        const labelBg = isDark ? '#e0e0e0' : '#333333';
        const labelText = isDark ? '#222222' : '#ffffff';

        container.querySelectorAll('.chart-card[data-chart-key]').forEach(function (card) {
            const c = chartData[card.getAttribute('data-chart-key')];
            const canvas = card.querySelector('canvas');
            if (!c || !canvas || !Array.isArray(c.points)) return;

            const isBar = c.type === 'bar';
            const points = c.points;
            const showLabels = points.length <= 40; // při větším počtu bodů by se popisky překrývaly

            // Popisky hodnot nad body / sloupci
            const valueLabels = {
                id: 'valueLabels',
                afterDatasetsDraw: function (chart) {
                    if (!showLabels) return;
                    const ctx = chart.ctx;
                    const meta = chart.getDatasetMeta(0);
                    ctx.save();
                    ctx.font = 'bold 11px sans-serif';
                    ctx.textAlign = 'center';
                    ctx.textBaseline = 'middle';
                    meta.data.forEach(function (el, i) {
                        const txt = (isBar ? '+' : '') + points[i].y;
                        if (isBar) {
                            ctx.fillStyle = '#16a085';
                            ctx.fillText(txt, el.x, el.y - 10);
                        } else {
                            const w = ctx.measureText(txt).width + 10;
                            const x = el.x - w / 2, y = el.y - 24, h = 16, r = 4;
                            ctx.fillStyle = labelBg;
                            ctx.beginPath();
                            ctx.moveTo(x + r, y);
                            ctx.arcTo(x + w, y, x + w, y + h, r);
                            ctx.arcTo(x + w, y + h, x, y + h, r);
                            ctx.arcTo(x, y + h, x, y, r);
                            ctx.arcTo(x, y, x + w, y, r);
                            ctx.closePath();
                            ctx.fill();
                            ctx.fillStyle = labelText;
                            ctx.fillText(txt, el.x, y + h / 2 + 0.5);
                        }
                    });
                    ctx.restore();
                }
            };

            new Chart(canvas, {
                type: isBar ? 'bar' : 'line',
                data: {
                    labels: points.map(function (p) { return fmtShort(p.x); }),
                    datasets: [{
                        label: c.title,
                        data: points.map(function (p) { return p.y; }),
                        borderColor: isBar ? '#16a085' : '#2980b9',
                        borderWidth: isBar ? 0 : 3,
                        backgroundColor: isBar ? '#16a085' : function (context) {
                            const area = context.chart.chartArea;
                            if (!area) return 'rgba(41,128,185,0.3)';
                            const g = context.chart.ctx.createLinearGradient(0, area.top, 0, area.bottom);
                            g.addColorStop(0, 'rgba(41,128,185,0.6)');
                            g.addColorStop(1, 'rgba(41,128,185,0.05)');
                            return g;
                        },
                        fill: !isBar,
                        tension: 0,
                        borderRadius: isBar ? 5 : 0,
                        maxBarThickness: 40,
                        pointRadius: isBar ? 0 : 4,
                        pointHoverRadius: isBar ? 0 : 6,
                        pointBackgroundColor: markerBg,
                        pointBorderColor: '#2980b9',
                        pointBorderWidth: 2
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    layout: { padding: { top: 30, right: 28, left: 6 } },
                    interaction: { mode: 'index', intersect: false },
                    plugins: {
                        legend: { display: false },
                        tooltip: {
                            backgroundColor: isDark ? '#2a2a2a' : '#ffffff',
                            titleColor: isDark ? '#e0e0e0' : '#333333',
                            bodyColor: isDark ? '#e0e0e0' : '#333333',
                            borderColor: isDark ? '#444444' : '#cccccc',
                            borderWidth: 1,
                            displayColors: false,
                            callbacks: {
                                title: function (items) { return fmtFull(points[items[0].dataIndex].x); },
                                label: function (item) {
                                    const p = points[item.dataIndex];
                                    if (isBar) {
                                        return '+' + p.y + (p.total !== undefined ? ' (Celkový stav: ' + p.total + ')' : '');
                                    }
                                    return String(p.y);
                                }
                            }
                        }
                    },
                    scales: {
                        x: {
                            grid: { display: false },
                            ticks: { color: axisText, font: { size: 11 }, maxRotation: 0, autoSkip: true, maxTicksLimit: 8 }
                        },
                        y: {
                            beginAtZero: isBar,
                            grid: { color: gridColor },
                            ticks: { color: axisText, font: { weight: 'bold' } }
                        }
                    }
                },
                plugins: [valueLabels]
            });
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
