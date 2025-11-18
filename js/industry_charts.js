// Industry Analytics Charts (uses industry_data.csv)
(function() {
  'use strict';

  // Render single Top-10 bar chart if payload exists
  const industryCanvas = document.getElementById("industryEmploymentChart")
  if (industryCanvas && window.__industryBarData && Array.isArray(window.__industryBarData.values)) {
    console.log('Rendering industry chart with data:', window.__industryBarData)
    const ctx = industryCanvas.getContext("2d")
    const labels = window.__industryBarData.labels || []
    const values = window.__industryBarData.values || []

    // eslint-disable-next-line no-undef
    new Chart(ctx, {
      type: 'bar',
      data: {
        labels,
        datasets: [{
          label: `MMTVTC Graduates`,
          data: values,
          backgroundColor: 'rgba(135, 206, 250, 0.6)',
          borderColor: 'rgba(0, 71, 171, 1)',
          borderWidth: 1
        }]
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: { legend: { display: true, position: 'top' } },
        scales: {
          x: { grid: { display: false }, ticks: { font: { size: 11 }, maxRotation: 45, minRotation: 0 } },
          y: { beginAtZero: true, grid: { color: 'rgba(0,0,0,0.1)' }, ticks: { font: { size: 11 } } }
        }
      }
    })
  } else if (industryCanvas) {
    console.warn('Industry chart missing data. window.__industryBarData =', window.__industryBarData)
  }

  // Render top program trend line with confidence range
  const trendCanvas = document.getElementById("industryTopTrendChart")
  if (trendCanvas && window.__industryBarData && window.__industryBarData.top) {
    const t = window.__industryBarData.top
    const years = (t.years || []).map(Number)
    const totals = (t.totals || []).map(Number)
    const lastYear = years.length ? years[years.length - 1] : null
    const predYear = (lastYear || 2025) + 1

    const pred = Number(t.pred || 0)
    const lower = Number(t.lower || 0)
    const upper = Number(t.upper || 0)

    const labels = years.concat([predYear])
    const histData = totals

    // eslint-disable-next-line no-undef
    let trendChart = new Chart(trendCanvas.getContext('2d'), {
      type: 'line',
      data: {
        labels,
        datasets: [
          {
            label: 'Historical',
            data: histData,
            borderColor: 'rgba(0, 102, 204, 1)',
            backgroundColor: 'rgba(0, 102, 204, 0.08)',
            pointBackgroundColor: 'rgba(0, 102, 204, 1)',
            tension: 0.2
          },
          {
            label: 'Prediction',
            data: new Array(histData.length - 1).fill(null).concat([histData[histData.length - 1], pred]),
            borderColor: 'rgba(220, 53, 69, 0.9)',
            backgroundColor: 'rgba(220, 53, 69, 0.1)',
            pointBackgroundColor: 'rgba(220, 53, 69, 0.9)',
            borderDash: [6, 6],
            tension: 0
          },
          // Confidence band using two datasets: upper filled to lower
          {
            label: 'Confidence Upper',
            data: new Array(histData.length - 1).fill(null).concat([null, upper]),
            borderColor: 'rgba(220, 53, 69, 0)',
            backgroundColor: 'rgba(220, 53, 69, 0.25)',
            pointRadius: 0,
            fill: '-1'
          },
          {
            label: 'Confidence Lower',
            data: new Array(histData.length - 1).fill(null).concat([null, lower]),
            borderColor: 'rgba(220, 53, 69, 0)',
            backgroundColor: 'rgba(220, 53, 69, 0)',
            pointRadius: 0
          }
        ]
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: { legend: { display: true, position: 'top' } },
        scales: {
          x: { grid: { display: false } },
          y: { beginAtZero: true, grid: { color: 'rgba(0,0,0,0.1)' } }
        }
      }
    })

    // Populate dropdown and wire change handler
    const select = document.getElementById('industryTrendSelect')
    const titleEl = document.getElementById('industryTrendTitle')
    if (select && Array.isArray(window.__industryBarData.topList)) {
      select.innerHTML = ''
      window.__industryBarData.topList.forEach((item, idx) => {
        const opt = document.createElement('option')
        opt.value = String(idx)
        opt.textContent = item.name
        select.appendChild(opt)
      })
      select.value = '0'

      select.addEventListener('change', () => {
        const idx = Number(select.value)
        const item = window.__industryBarData.topList[idx]
        if (!item) return
        const y = (item.years || []).map(Number)
        const totals2 = (item.totals || []).map(Number)
        const lastY = y.length ? y[y.length - 1] : null
        const pYear = (lastY || 2025) + 1
        const lbls = y.concat([pYear])

        // Update datasets in place
        trendChart.data.labels = lbls
        trendChart.data.datasets[0].data = totals2
        trendChart.data.datasets[1].data = new Array(totals2.length - 1).fill(null).concat([totals2[totals2.length - 1], Number(item.pred || 0)])
        trendChart.data.datasets[2].data = new Array(totals2.length - 1).fill(null).concat([null, Number(item.upper || 0)])
        trendChart.data.datasets[3].data = new Array(totals2.length - 1).fill(null).concat([null, Number(item.lower || 0)])
        titleEl && (titleEl.textContent = String(item.name).slice(0, 25) + '...')
        trendChart.update()
      })
    }
  }
})();
