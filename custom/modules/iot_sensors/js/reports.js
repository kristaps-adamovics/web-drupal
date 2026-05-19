(function (Drupal, drupalSettings, once) {
  Drupal.behaviors.iotSensorsReport = {
    attach(context) {
      once('iot-sensors-report', '#iot-sensors-report-chart', context).forEach((canvas) => {
        var data = drupalSettings.iotSensorsReport || { labels: [], series: [] };
        var empty = canvas.parentElement.querySelector('.iot-report-empty-chart');

        if (!data.labels.length || !data.series.length) {
          canvas.hidden = true;
          if (empty) empty.hidden = false;
          return;
        }

        if (empty) empty.hidden = true;
        drawChart(canvas, data);
      });
    },
  };

  function drawChart(canvas, data) {
    var COLORS = ['#378ADD', '#1D9E75', '#D85A30', '#7F77DD', '#BA7517', '#D4537E'];
    var DASH = [[], [6, 3], [3, 3], [8, 4, 2, 4], [], [4, 2]];

    var wrapper = canvas.parentElement;

    var legendEl = document.createElement('div');
    legendEl.style.cssText = 'display:flex;flex-wrap:wrap;gap:12px;margin-bottom:10px;font-size:12px;';
    wrapper.insertBefore(legendEl, canvas);

    var tooltipEl = document.createElement('div');
    tooltipEl.style.cssText = 'position:fixed;background:#fff;border:1px solid #e0e0e0;border-radius:8px;padding:8px 12px;font-size:12px;pointer-events:none;display:none;z-index:9999;box-shadow:0 2px 8px rgba(0,0,0,0.10);min-width:180px;';
    document.body.appendChild(tooltipEl);

    var co2Series = data.series.filter(function(s) {
      return s.label && s.label.toLowerCase().indexOf('co2') !== -1;
    });
    var otherSeries = data.series.filter(function(s) {
      return !s.label || s.label.toLowerCase().indexOf('co2') === -1;
    });

    var datasets = data.series.map(function(s, i) {
      var isCO2 = s.label && s.label.toLowerCase().indexOf('co2') !== -1;
      return {
        label: s.label,
        data: s.values,
        borderColor: COLORS[i % COLORS.length],
        backgroundColor: COLORS[i % COLORS.length] + '22',
        borderWidth: 2,
        borderDash: DASH[i % DASH.length],
        pointRadius: 4,
        pointHoverRadius: 6,
        pointBackgroundColor: COLORS[i % COLORS.length],
        tension: 0.35,
        fill: false,
        yAxisID: isCO2 ? 'y1' : 'y2',
        spanGaps: false,
      };
    });

    data.series.forEach(function(s, i) {
      var span = document.createElement('span');
      span.style.cssText = 'display:flex;align-items:center;gap:5px;cursor:pointer;font-size:12px;color:#555;';
      var dot = document.createElement('span');
      dot.style.cssText = 'width:18px;height:3px;background:' + COLORS[i % COLORS.length] + ';border-radius:2px;display:inline-block;flex-shrink:0;';
      span.appendChild(dot);
      span.appendChild(document.createTextNode(s.label));
      span.addEventListener('click', function() {
        var meta = chart.getDatasetMeta(i);
        meta.hidden = !meta.hidden;
        span.style.opacity = meta.hidden ? '0.35' : '1';
        chart.update();
      });
      legendEl.appendChild(span);
    });

    var canvasWrapper = document.createElement('div');
    canvasWrapper.style.cssText = 'position:relative;width:100%;height:320px;';
    wrapper.insertBefore(canvasWrapper, canvas);
    canvasWrapper.appendChild(canvas);

    var chart = new Chart(canvas, {
      type: 'line',
      data: {
        labels: data.labels,
        datasets: datasets,
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        interaction: { mode: 'index', intersect: false },
        plugins: {
          legend: { display: false },
          tooltip: {
            enabled: false,
            external: function(context) {
              var tt = context.tooltip;
              if (tt.opacity === 0) {
                tooltipEl.style.display = 'none';
                return;
              }
              var lines = tt.dataPoints.map(function(p) {
                return '<div style="display:flex;align-items:center;gap:6px;margin:2px 0;">' +
                  '<span style="width:8px;height:8px;border-radius:50%;background:' + p.dataset.borderColor + ';display:inline-block;flex-shrink:0;"></span>' +
                  '<span style="color:#888;font-size:11px;flex:1;">' + (p.dataset.label || '') + '</span>' +
                  '<span style="font-weight:500;">' + p.formattedValue + '</span>' +
                  '</div>';
              });
              tooltipEl.innerHTML = '<div style="font-size:11px;color:#888;margin-bottom:4px;font-weight:500;">' + (tt.title[0] || '') + '</div>' + lines.join('');
              tooltipEl.style.display = 'block';
              var rect = canvas.getBoundingClientRect();
              tooltipEl.style.left = (rect.left + tt.caretX + 14) + 'px';
              tooltipEl.style.top = (rect.top + tt.caretY - 10) + 'px';
            },
          },
        },
        scales: {
          x: {
            grid: { color: 'rgba(128,128,128,0.08)' },
            ticks: { font: { size: 11 }, color: '#888', maxRotation: 35, autoSkip: true, maxTicksLimit: 10 },
          },
          y1: {
            type: 'linear',
            position: 'left',
            title: { display: true, text: 'CO2 (ppm)', font: { size: 11 }, color: '#888' },
            grid: { color: 'rgba(128,128,128,0.08)' },
            ticks: { font: { size: 11 }, color: '#888' },
          },
          y2: {
            type: 'linear',
            position: 'right',
            title: { display: true, text: 'Temp (C) / Mitrums (%)', font: { size: 11 }, color: '#888' },
            grid: { drawOnChartArea: false },
            ticks: { font: { size: 11 }, color: '#888' },
          },
        },
      },
    });
  }
})(Drupal, drupalSettings, once);
