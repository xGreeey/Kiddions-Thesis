/**
 * Employment Rate Trend Analysis for MMTVTC
 * Analyzes and displays highest employment rates every 6 months using Linear Regression
 * Author: AI Assistant
 * Date: October 2025
 */

(function() {
  'use strict';

  // Employment Rate Trend Analysis Chart
  function initializeEmploymentTrendAnalysis() {
    const trendCanvas = document.getElementById("employmentTrendAnalysisChart");
    const courseSelect = document.getElementById("employmentTrendCourseSelect");
    const yearSelect = document.getElementById("employmentTrendYearSelect");
    const halfSelect = document.getElementById("employmentTrendHalfSelect");
    const info = document.getElementById("employmentTrendInfo");

    if (!trendCanvas) return;

    let trendChart = null;
    let dataset = null;

    const chartConfig = {
      responsive: true,
      maintainAspectRatio: false,
      aspectRatio: 2,
      resizeDelay: 200,
      plugins: {
        legend: {
          display: true,
          position: 'top',
          labels: {
            boxWidth: 12,
            padding: 15,
            font: { size: 12 }
          }
        },
        tooltip: {
          callbacks: {
            afterLabel: function(context) {
              if (context.datasetIndex === 1 && context.dataIndex === context.dataset.data.length - 1) {
                return 'Predicted 2026 H1';
              }
              return '';
            }
          }
        }
      },
      scales: {
        x: {
          grid: { display: false },
          ticks: { font: { size: 11 } },
          title: {
            display: true,
            text: 'Period (6-Month Intervals)'
          }
        },
        y: {
          beginAtZero: true,
          max: 100,
          grid: { color: 'rgba(0,0,0,0.1)' },
          ticks: { font: { size: 11 } },
          title: {
            display: true,
            text: 'Employment Rate (%)'
          }
        }
      },
      interaction: { intersect: false, mode: 'index' },
      elements: { point: { radius: 3, hoverRadius: 5 } }
    };

    async function loadCSV() {
      try {
        const res = await fetch("data/mmtvtc_employment_rates.csv?t=" + Date.now(), { cache: "no-store" });
        if (!res.ok) throw new Error("CSV not found");
        const text = await res.text();
        dataset = parseCSV(text);
        populateFilters();
        renderChart();
      } catch (error) {
        console.warn("Employment trend analysis CSV not available:", error);
        if (info) info.textContent = "No data available. Import CSV data to enable trend analysis.";
        
        if (trendChart) { trendChart.destroy(); trendChart = null; }
        
        // Show empty chart with message
        const ctx = trendCanvas.getContext('2d');
        ctx.fillStyle = '#f3f4f6';
        ctx.fillRect(0, 0, ctx.canvas.width, ctx.canvas.height);
        ctx.fillStyle = '#6b7280';
        ctx.font = '16px Arial';
        ctx.textAlign = 'center';
        ctx.fillText('No data available', ctx.canvas.width/2, ctx.canvas.height/2);
      }
    }

    function parseCSV(text) {
      const lines = text.split(/\r?\n/).filter((l) => l.trim().length);
      if (lines.length < 2) return { rows: [], courses: [], years: [] };
      
      const header = lines[0].split(",").map((h) => h.trim());
      const col = (name) => header.findIndex((h) => h.toLowerCase() === name);
      const idxYear = col("year");
      const idxCourse = col("course_name");
      const idxCode = col("course_code");
      const idxRate = col("employment_rate");
      
      const rows = [];
      const coursesSet = new Set();
      const yearsSet = new Set();
      
      for (let i = 1; i < lines.length; i++) {
        const parts = safeSplitCSV(lines[i], header.length);
        if (!parts || parts.length < header.length) continue;
        
        const year = Number(parts[idxYear]);
        const course = String(parts[idxCourse]);
        const code = String(parts[idxCode]);
        const rate = Number(parts[idxRate]);
        
        if (!Number.isFinite(year) || !course || !Number.isFinite(rate)) continue;
        
        rows.push({
          year,
          course_name: course,
          course_code: code,
          employment_rate: rate
        });
        
        coursesSet.add(course);
        yearsSet.add(year);
      }
      
      return {
        rows,
        courses: Array.from(coursesSet).sort(),
        years: Array.from(yearsSet).sort((a, b) => a - b)
      };
    }

    function safeSplitCSV(line, minCols) {
      const result = [];
      let current = "";
      let inQuotes = false;
      
      for (let i = 0; i < line.length; i++) {
        const ch = line[i];
        if (ch === '"') {
          if (inQuotes && line[i + 1] === '"') {
            current += '"';
            i++;
          } else {
            inQuotes = !inQuotes;
          }
        } else if (ch === "," && !inQuotes) {
          result.push(current);
          current = "";
        } else {
          current += ch;
        }
      }
      result.push(current);
      
      return result.length >= minCols ? result.map((s) => s.trim()) : null;
    }

    function createSemiannualData(courseData) {
      const semiannualData = [];
      
      for (const row of courseData) {
        const year = row.year;
        const rate = row.employment_rate;
        
        // Add H1 (first half)
        semiannualData.push({
          period: `${year} H1`,
          year: year,
          half: 1,
          employment_rate: rate,
          period_num: year * 2 - 1
        });
        
        // Add H2 (second half) - interpolate with next year if available
        const nextYearData = courseData.find(r => r.year === year + 1);
        const interpolatedRate = nextYearData ? (rate + nextYearData.employment_rate) / 2 : rate;
        
        semiannualData.push({
          period: `${year} H2`,
          year: year,
          half: 2,
          employment_rate: Math.round(interpolatedRate * 100) / 100,
          period_num: year * 2
        });
      }
      
      return semiannualData;
    }

    function applyLinearRegression(semiannualData) {
      if (semiannualData.length < 2) return null;
      
      const X = semiannualData.map(d => d.period_num);
      const y = semiannualData.map(d => d.employment_rate);
      
      // Simple linear regression
      const n = X.length;
      const sumX = X.reduce((s, v) => s + v, 0);
      const sumY = y.reduce((s, v) => s + v, 0);
      const sumXY = X.reduce((s, v, i) => s + v * y[i], 0);
      const sumXX = X.reduce((s, v) => s + v * v, 0);
      
      const denom = n * sumXX - sumX * sumX;
      const slope = denom !== 0 ? (n * sumXY - sumX * sumY) / denom : 0;
      const intercept = (sumY - slope * sumX) / n;
      
      // Calculate R²
      const yMean = sumY / n;
      const ssRes = y.reduce((s, v, i) => s + Math.pow(v - (slope * X[i] + intercept), 2), 0);
      const ssTot = y.reduce((s, v) => s + Math.pow(v - yMean, 2), 0);
      const r2 = ssTot > 0 ? 1 - (ssRes / ssTot) : 0;
      
      // Predict next period (2026 H1)
      const lastPeriodNum = Math.max(...X);
      const nextPeriodNum = lastPeriodNum + 1;
      const nextPrediction = Math.max(0, Math.min(100, slope * nextPeriodNum + intercept));
      
      return {
        slope,
        intercept,
        r2,
        nextPrediction: Math.round(nextPrediction * 100) / 100,
        trend: slope > 0 ? 'Increasing' : 'Decreasing'
      };
    }

    function populateFilters() {
      if (!dataset) return;
      
      // Populate course filter
      if (courseSelect) {
        courseSelect.innerHTML = '<option value="__ALL__">All Courses</option>';
        dataset.courses.forEach(course => {
          const option = document.createElement('option');
          option.value = course;
          option.textContent = course;
          courseSelect.appendChild(option);
        });
      }
      
      // Populate year filter
      if (yearSelect) {
        yearSelect.innerHTML = '<option value="__ALL__">All Years</option>';
        dataset.years.forEach(year => {
          const option = document.createElement('option');
          option.value = year;
          option.textContent = year;
          yearSelect.appendChild(option);
        });
      }
      
      // Populate half filter
      if (halfSelect) {
        halfSelect.innerHTML = `
          <option value="__ALL__">All Periods</option>
          <option value="1">First Half (H1)</option>
          <option value="2">Second Half (H2)</option>
        `;
      }
    }

    function renderChart() {
      if (!dataset || !dataset.rows.length) return;
      
      const selectedCourse = courseSelect ? courseSelect.value : "__ALL__";
      const selectedYear = yearSelect ? yearSelect.value : "__ALL__";
      const selectedHalf = halfSelect ? halfSelect.value : "__ALL__";
      
      // Filter data for display
      let filteredData = dataset.rows;
      
      if (selectedCourse !== "__ALL__") {
        filteredData = filteredData.filter(row => row.course_name === selectedCourse);
      }
      
      if (selectedYear !== "__ALL__") {
        filteredData = filteredData.filter(row => row.year === parseInt(selectedYear));
      }
      
      if (filteredData.length === 0) {
        if (trendChart) { trendChart.destroy(); trendChart = null; }
        return;
      }
      
      // Create semiannual data for display
      const semiannualData = createSemiannualData(filteredData);
      
      // Apply half filter to display data
      let displayData = semiannualData;
      if (selectedHalf !== "__ALL__") {
        displayData = semiannualData.filter(d => d.half === parseInt(selectedHalf));
      }
      
      if (displayData.length === 0) {
        if (trendChart) { trendChart.destroy(); trendChart = null; }
        return;
      }
      
      // For prediction calculation, use the full dataset (or at least the most recent data)
      // This ensures the 2026 prediction is always calculated from sufficient historical data
      let predictionData = dataset.rows;
      
      // If a specific course is selected, use that course's full history for prediction
      if (selectedCourse !== "__ALL__") {
        predictionData = predictionData.filter(row => row.course_name === selectedCourse);
      }
      
      // Create semiannual data for prediction (use full history)
      const predictionSemiannualData = createSemiannualData(predictionData);
      
      // Apply linear regression to full historical data for accurate prediction
      const regression = applyLinearRegression(predictionSemiannualData);
      
      // Prepare chart data
      const periods = displayData.map(d => d.period);
      const rates = displayData.map(d => d.employment_rate);
      
      const labels = [...periods, '2026 H1'];
      const historicalData = [...rates, null];
      const predictionDataArray = Array(periods.length - 1).fill(null).concat([
        rates[rates.length - 1] || 0,
        regression ? regression.nextPrediction : 0
      ]);
      
      const chartData = {
        labels,
        datasets: [
          {
            label: 'Historical Employment Rate (%)',
            data: historicalData,
            borderColor: '#3b82f6',
            backgroundColor: 'rgba(59, 130, 246, 0.1)',
            fill: true,
            tension: 0.25,
            pointRadius: 4,
            pointHoverRadius: 6,
            borderWidth: 3
          },
          {
            label: '2026 Prediction',
            data: predictionDataArray,
            borderColor: '#f97316',
            backgroundColor: 'rgba(249, 115, 22, 0.1)',
            fill: true,
            tension: 0.25,
            pointRadius: 5,
            pointHoverRadius: 7,
            borderWidth: 3,
            borderDash: [6, 4]
          }
        ]
      };
      
      // Destroy existing chart
      if (trendChart) { trendChart.destroy(); trendChart = null; }
      
      // Create new chart
      // eslint-disable-next-line no-undef
      trendChart = new Chart(trendCanvas.getContext('2d'), {
        type: 'line',
        data: chartData,
        options: chartConfig
      });
      
      // Update info
      if (info && regression) {
        const courseText = selectedCourse === "__ALL__" ? "All Courses" : selectedCourse;
        const yearText = selectedYear === "__ALL__" ? "All Years" : selectedYear;
        const halfText = selectedHalf === "__ALL__" ? "All Periods" : 
                        selectedHalf === "1" ? "First Half" : "Second Half";
        
        info.textContent = `${courseText} • ${yearText} • ${halfText} • Predicted 2026 H1: ${regression.nextPrediction}%`;
      }
    }

    function handleResize() {
      if (trendChart) trendChart.resize();
    }

    // Event listeners
    if (courseSelect) courseSelect.addEventListener('change', renderChart);
    if (yearSelect) yearSelect.addEventListener('change', renderChart);
    if (halfSelect) halfSelect.addEventListener('change', renderChart);
    
    let resizeTimeout;
    window.addEventListener('resize', () => {
      clearTimeout(resizeTimeout);
      resizeTimeout = setTimeout(handleResize, 300);
    });

    // Initialize
    loadCSV();
  }

  // Initialize when DOM is ready
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initializeEmploymentTrendAnalysis);
  } else {
    initializeEmploymentTrendAnalysis();
  }
})();
