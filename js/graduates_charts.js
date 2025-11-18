// Graduates Analytics Charts (uses Graduates_.csv)
(function() {
  'use strict';

  // Admin Career Analytics (mirrors student ML-based pipeline simplified to CSV-driven + forecast)
  function initializeCareerAnalyticsAdmin() {
    const courseSelect = document.getElementById("adminAnalyticsCourseSelect")
    const info = document.getElementById("adminAnalyticsInfo")
    const trendCtx = document.getElementById("adminAnalyticsTrendChart")?.getContext("2d")
    const forecastCtx = document.getElementById("adminAnalyticsForecastChart")?.getContext("2d")

    if (!courseSelect || !trendCtx || !forecastCtx) return

    let trendChart = null
    let forecastChart = null
    let dataset = null

    const chartConfig = {
      responsive: true,
      maintainAspectRatio: false,
      aspectRatio: 2,
      resizeDelay: 200,
      plugins: { legend: { display: true, position: 'top', labels: { boxWidth: 12, padding: 15, font: { size: 12 } } } },
      scales: { x: { grid: { display: false }, ticks: { font: { size: 11 } } }, y: { beginAtZero: true, grid: { color: 'rgba(0,0,0,0.1)' }, ticks: { font: { size: 11 } } } },
      interaction: { intersect: false, mode: 'index' },
      elements: { point: { radius: 3, hoverRadius: 5 } }
    }

    async function loadCSV() {
      try {
        const res = await fetch("data/Graduates_.csv?t=" + Date.now(), { cache: "no-store" })
        if (!res.ok) throw new Error("CSV not found")
        const text = await res.text()
        dataset = parseCSV(text)
        populateCourses(dataset)
        renderForSelection()
      } catch (_) {
        console.warn("Admin analytics CSV not available. Place it at htdocs/data/Graduates_.csv")
        if (info) info.textContent = "No data available. Import CSV data to enable analytics."
        
        // Show empty charts with message
        if (trendChart) { trendChart.destroy(); trendChart = null }
        if (forecastChart) { forecastChart.destroy(); forecastChart = null }
        
        // Create empty chart with message
        if (trendCtx) {
          trendCtx.fillStyle = '#f3f4f6'
          trendCtx.fillRect(0, 0, trendCtx.canvas.width, trendCtx.canvas.height)
          trendCtx.fillStyle = '#6b7280'
          trendCtx.font = '16px Arial'
          trendCtx.textAlign = 'center'
          trendCtx.fillText('No data available', trendCtx.canvas.width/2, trendCtx.canvas.height/2)
        }
        
        if (forecastCtx) {
          forecastCtx.fillStyle = '#f3f4f6'
          forecastCtx.fillRect(0, 0, forecastCtx.canvas.width, forecastCtx.canvas.height)
          forecastCtx.fillStyle = '#6b7280'
          forecastCtx.font = '16px Arial'
          forecastCtx.textAlign = 'center'
          forecastCtx.fillText('No data available', forecastCtx.canvas.width/2, forecastCtx.canvas.height/2)
        }
      }
    }

    function parseCSV(text) {
      const lines = text.split(/\r?\n/).filter((l) => l.trim().length)
      if (lines.length < 2) return { rows: [], courses: [], years: [] }
      const header = lines[0].split(",").map((h) => h.trim())
      const col = (name) => header.findIndex((h) => h.toLowerCase() === name)
      const idxYear = col("year"), idxCourse = col("course_id"), idxBatch = col("batch"), idxCount = col("student_count")
      const rows = [], coursesSet = new Set(), yearsSet = new Set()
      for (let i = 1; i < lines.length; i++) {
        const parts = safeSplitCSV(lines[i], header.length)
        if (!parts || parts.length < header.length) continue
        const year = Number(parts[idxYear])
        const course = String(parts[idxCourse])
        const batch = Number(parts[idxBatch])
        const count = Number(parts[idxCount])
        if (!Number.isFinite(year) || !course) continue
        rows.push({ year, course_id: course, batch, student_count: Number.isFinite(count) ? count : 0 })
        coursesSet.add(course)
        yearsSet.add(year)
      }
      return { rows, courses: Array.from(coursesSet).sort(), years: Array.from(yearsSet).sort((a, b) => a - b) }
    }

    function safeSplitCSV(line, minCols) {
      const result = []
      let current = "", inQuotes = false
      for (let i = 0; i < line.length; i++) {
        const ch = line[i]
        if (ch === '"') { if (inQuotes && line[i + 1] === '"') { current += '"'; i++ } else { inQuotes = !inQuotes } }
        else if (ch === "," && !inQuotes) { result.push(current); current = "" }
        else { current += ch }
      }
      result.push(current)
      return result.length >= minCols ? result.map((s) => s.trim()) : null
    }

    function populateCourses(data) {
      courseSelect.innerHTML = `<option value="__ALL__">All Courses</option>`
      data.courses.forEach((c) => {
        const opt = document.createElement("option"); opt.value = c; opt.textContent = c; courseSelect.appendChild(opt)
      })
    }

    function aggregate(data, selectedCourse) {
      const filtered = selectedCourse === "__ALL__" ? data.rows : data.rows.filter((r) => r.course_id === selectedCourse)
      const byYear = new Map()
      filtered.forEach((r) => { byYear.set(r.year, (byYear.get(r.year) || 0) + (r.student_count || 0)) })
      const years = Array.from(byYear.keys()).sort((a, b) => a - b)
      const totals = years.map((y) => byYear.get(y))
      return { years, totals }
    }

    function simpleForecast(years, totals, nextYear) {
      if (years.length < 2) return { predicted: totals[totals.length - 1] || 0, acc: null }
      const x = years.map((y) => y), y = totals, n = x.length
      const sumX = x.reduce((s, v) => s + v, 0)
      const sumY = y.reduce((s, v) => s + v, 0)
      const sumXY = x.reduce((s, v, i) => s + v * y[i], 0)
      const sumXX = x.reduce((s, v) => s + v * v, 0)
      const denom = n * sumXX - sumX * sumX
      const a = denom !== 0 ? (n * sumXY - sumX * sumY) / denom : 0
      const b = (sumY - a * sumX) / n
      const predicted = Math.max(0, Math.round(a * nextYear + b))
      let acc = null
      if (n >= 3) {
        const lastPred = Math.max(0, Math.round(a * x[n - 1] + b))
        const lastActual = y[n - 1]
        const mae = Math.abs(lastPred - lastActual)
        const base = Math.max(1, Math.abs(lastActual))
        acc = Math.max(0, 100 - (mae / base) * 100)
      }
      return { predicted, acc }
    }

    function renderCharts(selectedCourse) {
      const { years, totals } = aggregate(dataset, selectedCourse)
      const { predicted, acc } = simpleForecast(years, totals, 2026)
      if (trendChart) { trendChart.destroy(); trendChart = null }
      if (forecastChart) { forecastChart.destroy(); forecastChart = null }
      const trendLabels = years.map((y) => String(y)).concat(["2026"]) 
      const actualData = totals.concat([null])
      const predictionData = Array(Math.max(0, years.length - 1)).fill(null).concat([totals[totals.length - 1] || 0, predicted])

      const trendData = { 
        labels: trendLabels, 
        datasets: [
          { label: "Total Students", data: actualData, borderColor: "#1f77b4", backgroundColor: "rgba(31,119,180,0.15)", fill: true, tension: 0.25, pointRadius: 3, pointHoverRadius: 5, borderWidth: 2 },
          { label: "2026 Prediction", data: predictionData, borderColor: "#ff7f0e", backgroundColor: "rgba(255,127,14,0.15)", fill: true, tension: 0.25, pointRadius: 3, pointHoverRadius: 5, borderWidth: 2, borderDash: [6, 4] }
        ] 
      }
      // eslint-disable-next-line no-undef
      trendChart = new Chart(trendCtx, { type: "line", data: trendData, options: chartConfig })
      const forecastData = { labels: [String((years[years.length - 1] || 2025)), "2026"], datasets: [{ label: "Enrollment", data: [totals[totals.length - 1] || 0, predicted], backgroundColor: ["#1f77b4", "#ff7f0e"], borderColor: ["#1f77b4", "#ff7f0e"], borderWidth: 1 }] }
      // eslint-disable-next-line no-undef
      forecastChart = new Chart(forecastCtx, { type: "bar", data: forecastData, options: chartConfig })
      if (info) { const courseText = selectedCourse === "__ALL__" ? "All Courses" : selectedCourse; info.textContent = `Forecast for 2026 • ${courseText}${acc ? ` • Est. accuracy ~${acc.toFixed(1)}%` : ""}` }
    }

    function renderForSelection() { const selected = courseSelect.value || "__ALL__"; if (!dataset || !dataset.rows.length) return; renderCharts(selected) }

    function handleResize() { if (trendChart) trendChart.resize(); if (forecastChart) forecastChart.resize() }
    let resizeTimeout; window.addEventListener('resize', () => { clearTimeout(resizeTimeout); resizeTimeout = setTimeout(handleResize, 300) })
    courseSelect.addEventListener("change", renderForSelection)
    loadCSV()
  }

  // Initialize when DOM is ready
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initializeCareerAnalyticsAdmin)
  } else {
    initializeCareerAnalyticsAdmin()
  }
})();
