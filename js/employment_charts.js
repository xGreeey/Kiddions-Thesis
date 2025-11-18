// Employment Rate Prediction Charts
(function() {
  'use strict';

  // Employment Rate Bar Chart
  const employmentRateCanvas = document.getElementById("employmentRateChart")
  if (employmentRateCanvas && window.__employmentData && Array.isArray(window.__employmentData.predictions)) {
    console.log('Rendering employment rate chart with data:', window.__employmentData)
    const ctx = employmentRateCanvas.getContext("2d")
    const predictions = window.__employmentData.predictions || []
    
    // Get top 10 predictions for bar chart
    const top10 = predictions.slice(0, 10)
    const labels = top10.map(p => p.course_code === 'ALL' ? 'All Courses' : p.course_code)
    const values = top10.map(p => p.prediction_2026)
    const changes = top10.map(p => p.change)

    // eslint-disable-next-line no-undef
    new Chart(ctx, {
      type: 'bar',
      data: {
        labels,
        datasets: [{
          label: 'Employment Rate',
          data: values,
          backgroundColor: values.map((val, i) => {
            const change = changes[i]
            if (change > 0) return 'rgba(34, 197, 94, 0.8)' // Green for positive
            if (change < 0) return 'rgba(239, 68, 68, 0.8)' // Red for negative
            return 'rgba(156, 163, 175, 0.8)' // Gray for neutral
          }),
          borderColor: values.map((val, i) => {
            const change = changes[i]
            if (change > 0) return 'rgb(34, 197, 94)'
            if (change < 0) return 'rgb(239, 68, 68)'
            return 'rgb(156, 163, 175)'
          }),
          borderWidth: 2
        }]
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
          legend: {
            display: true,
            position: 'top'
          },
          tooltip: {
            callbacks: {
              afterLabel: function(context) {
                const index = context.dataIndex
                const change = changes[index]
                const changeText = change > 0 ? `+${change.toFixed(1)}%` : `${change.toFixed(1)}%`
                return `Change from 2025: ${changeText}`
              }
            }
          }
        },
        scales: {
          y: {
            beginAtZero: true,
            max: 100,
            title: {
              display: true,
              text: 'Employment Rate (%)'
            }
          },
          x: {
            title: {
              display: true,
              text: 'Course'
            }
          }
        }
      }
    })
  } else if (employmentRateCanvas) {
    console.warn('Employment rate chart missing data. window.__employmentData =', window.__employmentData)
  }

  // Employment Rate Trend Line Chart
  const employmentTrendCanvas = document.getElementById("employmentTrendChart")
  if (employmentTrendCanvas && window.__employmentData && window.__employmentData.top_course) {
    const topCourse = window.__employmentData.top_course
    const years = (topCourse.years || []).map(Number)
    const rates = (topCourse.rates || []).map(Number)
    const prediction = topCourse.prediction_2026 || 0
    const latestRate = topCourse.latest_rate || 0
    const change = topCourse.change || 0

    const labels = years.map(String).concat(['2026'])
    const histData = rates.concat([null])
    const predData = Array(rates.length - 1).fill(null).concat([latestRate, prediction])

    // eslint-disable-next-line no-undef
    let employmentTrendChart = new Chart(employmentTrendCanvas.getContext('2d'), {
      type: 'line',
      data: {
        labels,
        datasets: [
          {
            label: 'Historical Employment Rate (%)',
            data: histData,
            borderColor: '#1f77b4',
            backgroundColor: 'rgba(31, 119, 180, 0.1)',
            fill: true,
            tension: 0.25,
            pointRadius: 4,
            pointHoverRadius: 6,
            borderWidth: 3
          },
          {
            label: '2026 Prediction',
            data: predData,
            borderColor: '#ff7f0e',
            backgroundColor: 'rgba(255, 127, 14, 0.1)',
            fill: true,
            tension: 0.25,
            pointRadius: 5,
            pointHoverRadius: 7,
            borderWidth: 3,
            borderDash: [6, 4]
          }
        ]
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
          legend: {
            display: true,
            position: 'top'
          },
          tooltip: {
            callbacks: {
              afterLabel: function(context) {
                if (context.datasetIndex === 1 && context.dataIndex === labels.length - 1) {
                  return `Change from 2025: ${change > 0 ? '+' : ''}${change.toFixed(1)}%`
                }
                return ''
              }
            }
          }
        },
        scales: {
          y: {
            beginAtZero: true,
            max: 100,
            title: {
              display: true,
              text: 'Employment Rate (%)'
            }
          },
          x: {
            title: {
              display: true,
              text: 'Year'
            }
          }
        }
      }
    })

    // Populate dropdown and handle selection
    const trendSelect = document.getElementById("employmentTrendSelect")
    const trendTitle = document.getElementById("employmentTrendTitle")
    
    if (trendSelect && window.__employmentData && window.__employmentData.predictions) {
      const predictions = window.__employmentData.predictions
      
      // Populate dropdown
      trendSelect.innerHTML = predictions.map((p, i) => 
        `<option value="${i}">${p.course_code === 'ALL' ? 'All Courses Combined' : p.course_name} (${p.prediction_2026.toFixed(1)}%)</option>`
      ).join('')

      // Handle selection change
      trendSelect.addEventListener('change', function() {
        const selectedIndex = parseInt(this.value)
        const selectedCourse = predictions[selectedIndex]
        
        if (selectedCourse) {
          const y = selectedCourse.years || []
          const r = selectedCourse.rates || []
          const p = selectedCourse.prediction_2026 || 0
          const l = selectedCourse.latest_rate || 0
          const c = selectedCourse.change || 0

          const lbls = y.map(String).concat(['2026'])
          const histData2 = r.concat([null])
          const predData2 = Array(r.length - 1).fill(null).concat([l, p])

          // Update chart data
          employmentTrendChart.data.labels = lbls
          employmentTrendChart.data.datasets[0].data = histData2
          employmentTrendChart.data.datasets[1].data = predData2
          
          // Update title
          if (trendTitle) {
            const courseName = selectedCourse.course_code === 'ALL' ? 'All Courses Combined' : selectedCourse.course_name
            trendTitle.textContent = courseName.length > 25 ? courseName.substring(0, 25) + '...' : courseName
          }
          
          employmentTrendChart.update()
        }
      })
    }
  }
})();
