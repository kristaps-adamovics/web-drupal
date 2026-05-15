(function (Drupal, drupalSettings, once) {
  Drupal.behaviors.iotSensorsReport = {
    attach(context) {
      once('iot-sensors-report', '#iot-sensors-report-chart', context).forEach((canvas) => {
        var data = drupalSettings.iotSensorsReport || { labels: [], series: [] };
        var empty = canvas.parentElement.querySelector('.iot-report-empty-chart');

        if (!data.labels.length || !data.series.length) {
          canvas.hidden = true;
          empty.hidden = false;
          return;
        }

        empty.hidden = true;
        drawChart(canvas, data);
      });
    },
  };

  function drawChart(canvas, data) {
    var ctx = canvas.getContext('2d');
    var width = canvas.clientWidth || canvas.parentElement.clientWidth || 900;
    var height = Number(canvas.getAttribute('height')) || 320;
    var ratio = window.devicePixelRatio || 1;
    var padding = { top: 28, right: 28, bottom: 72, left: 64 };
    var colors = ['blue', 'red', 'green', 'purple', 'orange', 'teal'];

    canvas.width = width * ratio;
    canvas.height = height * ratio;
    ctx.scale(ratio, ratio);
    ctx.clearRect(0, 0, width, height);
    ctx.font = '12px Arial, sans-serif';
    ctx.lineWidth = 2;

    var all = data.series.flatMap((item) => item.values.filter((value) => value !== null));
    var min = Math.min(...all);
    var max = Math.max(...all);
    var spread = max - min || 1;
    var yMin = min - spread * 0.08;
    var yMax = max + spread * 0.08;
    var plotWidth = width - padding.left - padding.right;
    var plotHeight = height - padding.top - padding.bottom;

    ctx.strokeStyle = '#d7dde5';
    ctx.fillStyle = '#425466';
    ctx.beginPath();
    ctx.moveTo(padding.left, padding.top);
    ctx.lineTo(padding.left, padding.top + plotHeight);
    ctx.lineTo(padding.left + plotWidth, padding.top + plotHeight);
    ctx.stroke();

    for (var i = 0; i <= 4; i++) {
      var y = padding.top + plotHeight - (plotHeight * i / 4);
      var value = yMin + ((yMax - yMin) * i / 4);
      ctx.strokeStyle = '#edf1f5';
      ctx.beginPath();
      ctx.moveTo(padding.left, y);
      ctx.lineTo(padding.left + plotWidth, y);
      ctx.stroke();
      ctx.fillStyle = '#5f6f7f';
      ctx.fillText(value.toFixed(1), 12, y + 4);
    }

    var step = data.labels.length > 1 ? plotWidth / (data.labels.length - 1) : plotWidth;

    data.series.forEach((item, index) => {
      ctx.strokeStyle = colors[index % colors.length];
      ctx.fillStyle = colors[index % colors.length];
      ctx.beginPath();

      item.values.forEach((value, valueIndex) => {
        if (value === null) {
          return;
        }
        var x = padding.left + (step * valueIndex);
        var y = padding.top + plotHeight - ((value - yMin) / (yMax - yMin) * plotHeight);

        if (valueIndex === 0 || item.values[valueIndex - 1] === null) {
          ctx.moveTo(x, y);
        }
        else {
          ctx.lineTo(x, y);
        }
      });

      ctx.stroke();

      item.values.forEach((value, valueIndex) => {
        if (value === null) {
          return;
        }
        var x = padding.left + (step * valueIndex);
        var y = padding.top + plotHeight - ((value - yMin) / (yMax - yMin) * plotHeight);
        ctx.beginPath();
        ctx.arc(x, y, 3, 0, Math.PI * 2);
        ctx.fill();
      });
    });

    var labelEvery = Math.max(1, Math.ceil(data.labels.length / 8));
    ctx.fillStyle = '#425466';
    data.labels.forEach((label, index) => {
      if (index % labelEvery !== 0 && index !== data.labels.length - 1) {
        return;
      }
      var x = padding.left + (step * index);
      ctx.save();
      ctx.translate(x, height - 52);
      ctx.rotate(-Math.PI / 5);
      ctx.fillText(label, 0, 0);
      ctx.restore();
    });

    var legendX = padding.left;
    var legendY = 16;
    data.series.slice(0, 6).forEach((item, index) => {
      ctx.fillStyle = colors[index % colors.length];
      ctx.fillRect(legendX, legendY - 9, 10, 10);
      ctx.fillStyle = '#25313d';
      ctx.fillText(item.label, legendX + 16, legendY);
      legendX += ctx.measureText(item.label).width + 36;

      if (legendX > width - 180) {
        legendX = padding.left;
        legendY += 18;
      }
    });
  }
})(Drupal, drupalSettings, once);
