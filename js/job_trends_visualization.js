/**
 * Job Trends Visualization - Historical Data + 6-Month Prediction
 * SOO6 & SOO6.1: Visualizing current and predicted job/industry trends
 * Author: AI Assistant
 * Date: October 2025
 */

(function() {
  'use strict';

  // Job Trends Visualization Chart
  function initializeJobTrendsVisualization() {
    const trendsCanvas = document.getElementById("jobTrendsChart");
    const jobSelect = document.getElementById("jobTrendsJobSelect");
    const yearSelect = document.getElementById("jobTrendsYearSelect");
    const halfSelect = document.getElementById("jobTrendsHalfSelect");
    const info = document.getElementById("jobTrendsInfo");

    // Check if all elements are found
    if (!trendsCanvas || !jobSelect || !yearSelect || !halfSelect || !info) {
      console.warn('Some Job Trends elements not found');
    }

    // Ensure year select is visible
    if (yearSelect) {
      yearSelect.style.display = 'block';
      yearSelect.style.visibility = 'visible';
      yearSelect.style.opacity = '1';
      console.log('Year select is visible');
    }

    if (!trendsCanvas) return;

    let trendsChart = null;
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
                return '2026 H1 Prediction';
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
          grid: { color: 'rgba(0,0,0,0.1)' },
          ticks: { font: { size: 11 } },
          title: {
            display: true,
            text: 'Number of Students'
          }
        }
      },
      interaction: { intersect: false, mode: 'index' },
      elements: { point: { radius: 3, hoverRadius: 5 } }
    };

    async function loadCSV() {
      try {
        const res = await fetch("data/industry_data.csv?t=" + Date.now(), { cache: "no-store" });
        if (!res.ok) throw new Error("CSV not found");
        const text = await res.text();
        dataset = parseCSV(text);
        populateFilters();
        renderChart();
      } catch (error) {
        console.warn("Job trends CSV not available:", error);
        if (info) info.textContent = "No data available. Import CSV data to enable job trends analysis.";
        
        if (trendsChart) { trendsChart.destroy(); trendsChart = null; }
        
        // Show empty chart with message
        const ctx = trendsCanvas.getContext('2d');
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
      if (lines.length < 2) return { rows: [], jobs: [], years: [] };
      
      const header = lines[0].split(",").map((h) => h.trim());
      const col = (name) => header.findIndex((h) => h.toLowerCase() === name);
      const idxYear = col("year");
      const idxIndustry = col("industry_id");
      const idxBatch = col("batch");
      const idxCount = col("student_count");
      
      const rows = [];
      const jobsSet = new Set();
      const yearsSet = new Set();
      
      for (let i = 1; i < lines.length; i++) {
        const parts = safeSplitCSV(lines[i], header.length);
        if (!parts || parts.length < header.length) continue;
        
        const year = Number(parts[idxYear]);
        const industry = String(parts[idxIndustry]);
        const batch = Number(parts[idxBatch]);
        const count = Number(parts[idxCount]);
        
        if (!Number.isFinite(year) || !industry || !Number.isFinite(batch) || !Number.isFinite(count)) continue;
        
        rows.push({
          year,
          industry_id: industry,
          batch,
          student_count: count
        });
        
        jobsSet.add(industry);
        yearsSet.add(year);
      }
      
      return {
        rows,
        jobs: Array.from(jobsSet).sort(),
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

    function create6MonthPeriods(jobData) {
      const sixMonthData = [];
      
      // Sort data by year and batch
      const sortedData = jobData.sort((a, b) => {
        if (a.year !== b.year) return a.year - b.year;
        return a.batch - b.batch;
      });
      
      for (const year of [...new Set(sortedData.map(d => d.year))].sort()) {
        const yearData = sortedData.filter(d => d.year === year);
        
        // H1: Batch 1 + Batch 2 (January - June)
        const h1Batches = yearData.filter(d => d.batch === 1 || d.batch === 2);
        if (h1Batches.length > 0) {
          const h1Students = h1Batches.reduce((sum, d) => sum + d.student_count, 0);
          sixMonthData.push({
            period: `${year} H1`,
            year: year,
            half: 1,
            student_count: h1Students,
            period_num: year * 2 - 1
          });
        }
        
        // H2: Batch 3 + Next Year Batch 1 (July - December)
        const batch3 = yearData.filter(d => d.batch === 3);
        if (batch3.length > 0) {
          let h2Students = batch3.reduce((sum, d) => sum + d.student_count, 0);
          
          // Add next year's batch 1 if available
          const nextYearData = sortedData.filter(d => d.year === year + 1 && d.batch === 1);
          if (nextYearData.length > 0) {
            h2Students += nextYearData.reduce((sum, d) => sum + d.student_count, 0);
          }
          
          sixMonthData.push({
            period: `${year} H2`,
            year: year,
            half: 2,
            student_count: h2Students,
            period_num: year * 2
          });
        }
      }
      
      return sixMonthData;
    }

    function applyLinearRegression(sixMonthData) {
      if (sixMonthData.length < 2) return null;
      
      const X = sixMonthData.map(d => d.period_num);
      const y = sixMonthData.map(d => d.student_count);
      
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
      
      // Calculate predictions for historical data
      const predictions = X.map(x => slope * x + intercept);
      
      // Predict next period (2026 H1)
      const lastPeriodNum = Math.max(...X);
      const nextPeriodNum = lastPeriodNum + 1;
      const nextPrediction = Math.max(0, slope * nextPeriodNum + intercept);
      
      return {
        slope,
        intercept,
        r2,
        predictions,
        nextPrediction: Math.round(nextPrediction * 10) / 10,
        trend: slope > 0 ? 'Increasing' : 'Decreasing'
      };
    }

    function populateFilters() {
      if (!dataset) return;
      
      // Populate job filter
      if (jobSelect) {
        jobSelect.innerHTML = '<option value="__ALL__">All Jobs/Industries</option>';
        dataset.jobs.forEach(job => {
          const option = document.createElement('option');
          option.value = job;
          option.textContent = job;
          jobSelect.appendChild(option);
        });
      }
      
      // Populate year filter
      if (yearSelect) {
          yearSelect.style.display = 'block';
        yearSelect.style.visibility = 'visible';
        yearSelect.style.opacity = '1';
        yearSelect.innerHTML = '<option value="__ALL__">All Years</option>';
        dataset.years.forEach(year => {
          const option = document.createElement('option');
          option.value = year;
          option.textContent = year;
          yearSelect.appendChild(option);
        });
      } else {
        console.error('Year select element not found!');
        // Try to find it again
        const yearSelectRetry = document.getElementById("jobTrendsYearSelect");
        console.log('Retry finding year select:', yearSelectRetry);
        
        if (!yearSelectRetry) {
          console.log('Creating year select element manually');
          const filtersContainer = document.querySelector('#jobTrendsJobSelect').parentElement;
          if (filtersContainer) {
            const yearSelectNew = document.createElement('select');
            yearSelectNew.id = 'jobTrendsYearSelect';
            yearSelectNew.className = 'filter-select';
            yearSelectNew.style.display = 'block';
            yearSelectNew.innerHTML = '<option value="__ALL__">All Years</option>';
            
            // Insert after job select
            const jobSelect = document.getElementById('jobTrendsJobSelect');
            jobSelect.parentNode.insertBefore(yearSelectNew, jobSelect.nextSibling);
            
            // Populate it
            dataset.years.forEach(year => {
              const option = document.createElement('option');
              option.value = year;
              option.textContent = year;
              yearSelectNew.appendChild(option);
            });
            
            console.log('Year select created and populated');
          }
        }
      }
      
      // Populate half filter
      if (halfSelect) {
        halfSelect.innerHTML = `
          <option value="__ALL__">All Periods</option>
          <option value="1">First Half (H1) - Jan-Jun</option>
          <option value="2">Second Half (H2) - Jul-Dec</option>
        `;
      }
    }

    function renderChart() {
      if (!dataset || !dataset.rows.length) return;
      
      const selectedJob = jobSelect ? jobSelect.value : "__ALL__";
      const selectedYear = yearSelect ? yearSelect.value : "__ALL__";
      const selectedHalf = halfSelect ? halfSelect.value : "__ALL__";
      
      // Filter data for display
      let filteredData = dataset.rows;
      
      if (selectedJob !== "__ALL__") {
        filteredData = filteredData.filter(row => row.industry_id === selectedJob);
      }
      
      if (selectedYear !== "__ALL__") {
        filteredData = filteredData.filter(row => row.year === parseInt(selectedYear));
      }
      
      if (filteredData.length === 0) {
        if (trendsChart) { trendsChart.destroy(); trendsChart = null; }
        return;
      }
      
      // Group by job and create 6-month periods for display
      const jobGroups = {};
      filteredData.forEach(row => {
        if (!jobGroups[row.industry_id]) {
          jobGroups[row.industry_id] = [];
        }
        jobGroups[row.industry_id].push(row);
      });
      
      console.log('Filtered data and job groups:', {
        filteredData: filteredData.length,
        jobGroups: Object.keys(jobGroups),
        selectedJob,
        selectedYear,
        selectedHalf
      });
      
      // Create visualization for each job or combined
      let allSixMonthData = [];
      
      if (selectedJob !== "__ALL__") {
        // Single job analysis
        const jobData = jobGroups[selectedJob] || [];
        console.log('Single job data:', jobData);
        
        allSixMonthData = create6MonthPeriods(jobData);
        console.log('Created 6-month periods:', allSixMonthData);
        
        // Apply half filter
        if (selectedHalf !== "__ALL__") {
          allSixMonthData = allSixMonthData.filter(d => d.half === parseInt(selectedHalf));
          console.log('After half filter:', allSixMonthData);
        }
      } else {
        // Combined analysis for all jobs
        for (const [jobId, jobData] of Object.entries(jobGroups)) {
          const sixMonthData = create6MonthPeriods(jobData);
          
          // Apply half filter
          let filteredSixMonthData = sixMonthData;
          if (selectedHalf !== "__ALL__") {
            filteredSixMonthData = sixMonthData.filter(d => d.half === parseInt(selectedHalf));
          }
          
          allSixMonthData = allSixMonthData.concat(filteredSixMonthData);
        }
        
        // Sort by period
        allSixMonthData.sort((a, b) => a.period_num - b.period_num);
      }
      
      if (allSixMonthData.length === 0) {
        if (trendsChart) { trendsChart.destroy(); trendsChart = null; }
        return;
      }
      
      // For prediction calculation, use the full dataset (or at least the most recent data)
      // This ensures the 2026 prediction is always calculated from sufficient historical data
      let predictionData = dataset.rows;
      
      // If a specific job is selected, use that job's full history for prediction
      if (selectedJob !== "__ALL__") {
        predictionData = predictionData.filter(row => row.industry_id === selectedJob);
      }
      
      // Group prediction data by job
      const predictionJobGroups = {};
      predictionData.forEach(row => {
        if (!predictionJobGroups[row.industry_id]) {
          predictionJobGroups[row.industry_id] = [];
        }
        predictionJobGroups[row.industry_id].push(row);
      });
      
      // Calculate predictions using full historical data
      let regressionResults = {};
      if (selectedJob !== "__ALL__") {
        // Single job prediction using full history
        const jobData = predictionJobGroups[selectedJob] || [];
        const sixMonthData = create6MonthPeriods(jobData);
        regressionResults[selectedJob] = applyLinearRegression(sixMonthData);
      } else {
        // Combined analysis for all jobs using full history
        for (const [jobId, jobData] of Object.entries(predictionJobGroups)) {
          const sixMonthData = create6MonthPeriods(jobData);
          regressionResults[jobId] = applyLinearRegression(sixMonthData);
        }
      }
      
      // Prepare chart data
      const periods = allSixMonthData.map(d => d.period);
      const studentCounts = allSixMonthData.map(d => d.student_count);
      
      console.log('Chart data preparation:', {
        periods,
        studentCounts,
        allSixMonthData,
        selectedJob,
        selectedYear,
        selectedHalf
      });
      
      // If we have no historical data, show a message
      if (periods.length === 0 || studentCounts.length === 0) {
        console.warn('No historical data available for the selected filters');
        if (trendsChart) { trendsChart.destroy(); trendsChart = null; }
        return;
      }
      
      const labels = [...periods, '2026 H1'];
      const historicalData = [...studentCounts, null];
      
      // Calculate combined prediction for 2026 H1 using full historical data
      let nextPrediction = 0;
      if (selectedJob !== "__ALL__") {
        const regression = regressionResults[selectedJob];
        nextPrediction = regression ? regression.nextPrediction : 0;
      } else {
        // Average prediction across all jobs using full history
        const validPredictions = Object.values(regressionResults)
          .filter(r => r && r.nextPrediction > 0)
          .map(r => r.nextPrediction);
        nextPrediction = validPredictions.length > 0 
          ? validPredictions.reduce((sum, p) => sum + p, 0) / validPredictions.length 
          : 0;
      }
      
      const predictionDataArray = Array(periods.length).fill(null).concat([nextPrediction]);
      
      console.log('Final chart data:', {
        labels,
        historicalData,
        predictionData,
        nextPrediction
      });
      
      const chartData = {
        labels,
        datasets: [
          {
            label: 'Historical Student Count',
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
            label: '2026 H1 Prediction',
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
      
      console.log('Chart data structure:', chartData);
      
      // Destroy existing chart
      if (trendsChart) { trendsChart.destroy(); trendsChart = null; }
      
      // Create new chart
      // eslint-disable-next-line no-undef
      trendsChart = new Chart(trendsCanvas.getContext('2d'), {
        type: 'line',
        data: chartData,
        options: chartConfig
      });
      
      // Update info
      if (info) {
        const jobText = selectedJob === "__ALL__" ? "All Jobs/Industries" : selectedJob;
        const yearText = selectedYear === "__ALL__" ? "All Years" : selectedYear;
        const halfText = selectedHalf === "__ALL__" ? "All Periods" : 
                        selectedHalf === "1" ? "First Half (H1)" : "Second Half (H2)";
        
        let infoText = `${jobText} • ${yearText} • ${halfText} • Predicted 2026 H1: ${Math.round(nextPrediction)} students`;
        
        if (selectedJob !== "__ALL__" && regressionResults[selectedJob]) {
          const regression = regressionResults[selectedJob];
          infoText += ` • R² = ${(regression.r2 * 100).toFixed(1)}% • ${regression.trend}`;
        }
        
        info.textContent = infoText;
      }
    }

    function handleResize() {
      if (trendsChart) trendsChart.resize();
    }

    // Event listeners
    if (jobSelect) jobSelect.addEventListener('change', renderChart);
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
      document.addEventListener('DOMContentLoaded', () => {
        console.log('DOM loaded, initializing Job Trends Visualization');
        setTimeout(initializeJobTrendsVisualization, 100);
      });
    } else {
      console.log('DOM already loaded, initializing Job Trends Visualization');
      setTimeout(initializeJobTrendsVisualization, 100);
    }
})();
