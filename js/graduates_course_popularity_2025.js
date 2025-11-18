// Course Popularity for 2025 (memory-efficient, CSV-driven, Chart.js)
(function() {
	'use strict';

	const barCanvas = document.getElementById('coursePopularity2025Bar')
	const pieCanvas = document.getElementById('coursePopularity2025Pie')
	const summaryEl = document.getElementById('coursePopularity2025Summary')

	if (!barCanvas && !pieCanvas && !summaryEl) return

	function safeSplitCSV(line, expected) {
		const out = []
		let cur = '', inQ = false
		for (let i = 0; i < line.length; i++) {
			const ch = line[i]
			if (ch === '"') {
				if (inQ && line[i+1] === '"') { cur += '"'; i++ } else { inQ = !inQ }
			} else if (ch === ',' && !inQ) {
				out.push(cur); cur = ''
			} else {
				cur += ch
			}
		}
		out.push(cur)
		return out.length >= expected ? out.map(s => s.trim()) : null
	}

	function parseCSV(text) {
		const lines = text.split(/\r?\n/).filter(l => l.trim().length)
		if (lines.length < 2) return []
		const header = lines[0].split(',').map(h => h.trim().toLowerCase())
		const idxYear = header.indexOf('year')
		const idxCourse = header.indexOf('course_id')
		const idxCount = header.indexOf('student_count')
		if (idxYear < 0 || idxCourse < 0 || idxCount < 0) return []
		const rows = []
		for (let i = 1; i < lines.length; i++) {
			const parts = safeSplitCSV(lines[i], header.length)
			if (!parts) continue
			const year = Number(parts[idxYear])
			if (year !== 2025) continue
			const course = String(parts[idxCourse] || '')
			if (!course) continue
			const count = Number(parts[idxCount])
			rows.push({ course, count: Number.isFinite(count) ? count : 0 })
		}
		return rows
	}

	function aggregate(rows) {
		const map = new Map()
		for (const r of rows) { map.set(r.course, (map.get(r.course) || 0) + r.count) }
		const entries = Array.from(map.entries())
		entries.sort((a, b) => b[1] - a[1])
		return entries
	}

	function renderBar(ctx, labels, values) {
		if (!ctx) return
		// eslint-disable-next-line no-undef
		new Chart(ctx, {
			type: 'bar',
			data: {
				labels,
				datasets: [{
					label: 'Total Students (2025)',
					data: values,
					backgroundColor: labels.map((_, i) => `hsl(${(i * 37) % 360} 70% 60% / 0.75)`),
					borderColor: labels.map((_, i) => `hsl(${(i * 37) % 360} 70% 35%)`),
					borderWidth: 1,
					borderRadius: 6,
					barPercentage: 0.8,
					categoryPercentage: 0.7
				}]
			},
			options: {
				responsive: true,
				maintainAspectRatio: false,
				plugins: {
					legend: {
						display: true,
						position: 'top',
						labels: { boxWidth: 12, boxHeight: 12, usePointStyle: true }
					},
					title: {
						display: false
					},
					tooltip: {
						callbacks: {
							label: function(context) {
								const value = Number(context.parsed.y) || 0
								return ` ${value.toLocaleString()} students`
							},
							afterLabel: function(context) {
								const dataset = context.dataset
								const data = Array.isArray(dataset?.data) ? dataset.data : []
								const total = data.reduce((s, v) => s + Number(v || 0), 0)
								const value = Number(context.parsed.y) || 0
								const pct = total > 0 ? (value / total * 100) : 0
								return ` (${pct.toFixed(1)}%)`
							}
						}
					}
				},
				scales: {
					x: {
						grid: { display: false },
						ticks: {
							maxRotation: 30,
							minRotation: 0,
							autoSkip: true,
							callback: function(value, index) {
								const label = labels[index] || ''
								return label.length > 20 ? label.slice(0, 20) + '…' : label
							}
						},
						title: { display: true, text: 'Course', color: '#6b7280', font: { weight: '500' } }
					},
					y: {
						beginAtZero: true,
						grid: { color: 'rgba(0,0,0,0.06)' },
						ticks: {
							precision: 0,
							callback: function(value) { return Number(value).toLocaleString() }
						},
						title: { display: true, text: 'Students', color: '#6b7280', font: { weight: '500' } }
					}
				}
			}
		})
	}

	function renderPie(ctx, labels, values) {
		if (!ctx) return
		// eslint-disable-next-line no-undef
		new Chart(ctx, {
			type: 'pie',
			data: { labels, datasets: [{ data: values, backgroundColor: labels.map((_,i)=>`hsl(${(i*37)%360} 75% 65% / 0.85)`), borderColor: 'rgba(255,255,255,0.9)', borderWidth: 2 }] },
			options: {
				responsive: true,
				maintainAspectRatio: false,
				plugins: {
					legend: { position: 'right' },
					tooltip: {
						callbacks: {
							label: function(context) {
								const label = context.label || ''
								const value = Number(context.parsed) || 0
								const total = (context.dataset && Array.isArray(context.dataset.data)) ? context.dataset.data.reduce((s, v) => s + Number(v || 0), 0) : 0
								const pct = total > 0 ? (value / total * 100) : 0
								return `${label}: ${value} (${pct.toFixed(1)}%)`
							}
						}
					}
				}
			}
		})
	}

	function renderSummary(entries) {
		if (!summaryEl) return
		const totalCourses = entries.length
		const totalStudents = entries.reduce((s, e) => s + e[1], 0)
		const top5 = entries.slice(0, 5)
		const bottom5 = entries.slice(-5)
		let html = ''
		html += `<div><strong>Total courses offered:</strong> ${totalCourses}</div>`
		html += `<div><strong>Total students enrolled:</strong> ${totalStudents}</div>`
		html += `<div style="margin-top:8px;"><strong>Top 5 Most Popular Courses:</strong></div>`
		html += '<ol style="margin:6px 0 12px 20px;">' + top5.map(([c,v]) => `<li>${c}: ${v} students</li>`).join('') + '</ol>'
		html += `<div><strong>Bottom 5 Least Popular Courses:</strong></div>`
		html += '<ol style="margin:6px 0 0 20px;">' + bottom5.map(([c,v]) => `<li>${c}: ${v} students</li>`).join('') + '</ol>'
		summaryEl.innerHTML = html
	}

	async function init() {
		try {
			const res = await fetch('data/Graduates_.csv?t=' + Date.now(), { cache: 'no-store' })
			if (!res.ok) throw new Error('CSV not found')
			const text = await res.text()
			const rows = parseCSV(text)
			if (!rows.length) { if (summaryEl) summaryEl.textContent = 'No 2025 data available.'; return }
			const entries = aggregate(rows)
			const labels = entries.map(e => e[0])
			const values = entries.map(e => e[1])

			// Bar chart: all courses
			renderBar(barCanvas?.getContext('2d'), labels, values)

			// Pie chart: top 10 + Others
			const topN = 10
			const topLabels = labels.slice(0, topN)
			const topValues = values.slice(0, topN)
			const others = values.slice(topN).reduce((s, v) => s + v, 0)
			if (others > 0) { topLabels.push('Others'); topValues.push(others) }
			renderPie(pieCanvas?.getContext('2d'), topLabels, topValues)

			// Summary
			renderSummary(entries)
		} catch (e) {
			console.warn('Failed to load data/Graduates_.csv:', e)
			if (summaryEl) summaryEl.textContent = 'No data available. Import CSV data to enable this section.'
			
			// Show empty charts with message
			if (barCanvas) {
				const ctx = barCanvas.getContext('2d')
				ctx.fillStyle = '#f3f4f6'
				ctx.fillRect(0, 0, barCanvas.width, barCanvas.height)
				ctx.fillStyle = '#6b7280'
				ctx.font = '16px Arial'
				ctx.textAlign = 'center'
				ctx.fillText('No data available', barCanvas.width/2, barCanvas.height/2)
			}
			
			if (pieCanvas) {
				const ctx = pieCanvas.getContext('2d')
				ctx.fillStyle = '#f3f4f6'
				ctx.fillRect(0, 0, pieCanvas.width, pieCanvas.height)
				ctx.fillStyle = '#6b7280'
				ctx.font = '16px Arial'
				ctx.textAlign = 'center'
				ctx.fillText('No data available', pieCanvas.width/2, pieCanvas.height/2)
			}
		}
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', init)
	} else {
		init()
	}
})();


