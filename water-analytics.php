<?php
require_once 'includes/functions.php';
requireRole('ANALYTICS');

$pageTitle = 'Water Analytics';
include 'includes/header.php';
?>

<div class="container">
    <div class="content-area">
        <h1>Water Analytics</h1>
        
        <div class="analytics-tabs">
            <button class="tab-button active" onclick="switchTab('questions')">Water Questions</button>
            <button class="tab-button" onclick="switchTab('availability')">Water Availability</button>
            <button class="tab-button" onclick="switchTab('export')">Export Data</button>
        </div>
        
        <!-- Water Questions Tab -->
        <div id="questions-tab" class="analytics-tab-content active">
            <h2>Water Questions Responses</h2>
            <div id="questions-charts-container" class="questions-charts-container">
                <!-- Charts will be dynamically generated here -->
            </div>
        </div>
        
        <!-- Water Availability Tab -->
        <div id="availability-tab" class="analytics-tab-content">
            <h2>Water Availability</h2>
            
            <div class="availability-controls">
                <div class="form-group">
                    <label for="period-type">Period Type:</label>
                    <select id="period-type" onchange="updateDateRange()">
                        <option value="daily">Daily</option>
                        <option value="weekly">Weekly</option>
                        <option value="monthly">Monthly</option>
                        <option value="annually">Annually</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="date-from">From Date:</label>
                    <input type="date" id="date-from" onchange="loadAvailabilityData()">
                </div>
                
                <div class="form-group">
                    <label for="date-to">To Date:</label>
                    <input type="date" id="date-to" onchange="loadAvailabilityData()">
                </div>
            </div>
            
            <div class="availability-charts">
                <div class="chart-container">
                    <h3>Availability Distribution</h3>
                    <canvas id="availabilityPieChart"></canvas>
                </div>
                
                <div class="chart-container">
                    <h3>Response Rate</h3>
                    <canvas id="responseGaugeChart"></canvas>
                </div>
            </div>
        </div>
        
        <!-- Export Data Tab -->
        <div id="export-tab" class="analytics-tab-content">
            <h2>Export Water Availability Data</h2>
            
            <div class="export-controls">
                <div class="form-group">
                    <label for="export-date-from">From Date:</label>
                    <input type="date" id="export-date-from" onchange="loadExportData()">
                </div>
                
                <div class="form-group">
                    <label for="export-date-to">To Date:</label>
                    <input type="date" id="export-date-to" onchange="loadExportData()">
                </div>
                
                <div class="form-group">
                    <button type="button" class="btn btn-primary" onclick="exportToCSV()">Export</button>
                </div>
            </div>
            
            <div class="export-table-container">
                <table id="export-data-table" class="export-data-table">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Address</th>
                            <th>Latitude</th>
                            <th>Longitude</th>
                            <th>Water Available</th>
                        </tr>
                    </thead>
                    <tbody id="export-data-tbody">
                        <tr>
                            <td colspan="5" style="text-align: center; padding: 2rem; color: #666;">
                                Select date range and data will load automatically
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Chart.js library -->
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>

<script>
let questionsCharts = {}; // Store all question charts
let availabilityPieChart = null;
let responseGaugeChart = null;

// Export data storage
let exportData = [];

// Initialize on page load
document.addEventListener('DOMContentLoaded', function() {
    loadQuestionsData();
    initializeAvailabilityDates();
    initializeExportDates();
});

function switchTab(tabName) {
    // Hide all tabs
    document.querySelectorAll('.analytics-tab-content').forEach(tab => {
        tab.classList.remove('active');
    });
    document.querySelectorAll('.tab-button').forEach(btn => {
        btn.classList.remove('active');
    });
    
    // Show selected tab
    document.getElementById(tabName + '-tab').classList.add('active');
    const clickedButton = event.target;
    clickedButton.classList.add('active');
    
    // Load data for the tab
    if (tabName === 'questions') {
        loadQuestionsData();
    } else if (tabName === 'availability') {
        loadAvailabilityData();
    } else if (tabName === 'export') {
        loadExportData();
    }
}

function loadQuestionsData() {
    fetch('<?= baseUrl('/api/water-questions-analytics.php') ?>')
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                renderQuestionsChart(data.data);
            } else {
                console.error('Error loading questions data:', data.message);
            }
        })
        .catch(error => {
            console.error('Error loading questions data:', error);
        });
}

function renderQuestionsChart(data) {
    const container = document.getElementById('questions-charts-container');
    container.innerHTML = ''; // Clear existing charts
    
    // Destroy all existing charts
    Object.values(questionsCharts).forEach(chart => {
        if (chart) chart.destroy();
    });
    questionsCharts = {};
    
    data.forEach((question, index) => {
        const answerKeys = Object.keys(question.answers);
        const answerCount = answerKeys.length;
        const totalResponses = question.total_responses || 0;
        
        // Check if it's a yes/no question (exactly 2 options, typically yes/no)
        const isYesNo = answerCount === 2 && (
            answerKeys.some(key => key.toLowerCase().includes('yes')) &&
            answerKeys.some(key => key.toLowerCase().includes('no'))
        );
        
        // Create chart container
        const chartDiv = document.createElement('div');
        chartDiv.className = 'question-chart-container';
        chartDiv.innerHTML = `
            <h3>${escapeHtml(question.question_text)}</h3>
            <div class="question-chart-wrapper">
                <canvas id="question-chart-${question.question_id}"></canvas>
            </div>
            <div class="question-chart-stats">
                <strong>Total Responses:</strong> ${totalResponses}
            </div>
        `;
        container.appendChild(chartDiv);
        
        const ctx = document.getElementById(`question-chart-${question.question_id}`);
        
        if (isYesNo) {
            // Create dial/pie chart for yes/no questions
            const yesKey = answerKeys.find(key => key.toLowerCase().includes('yes')) || answerKeys[0];
            const noKey = answerKeys.find(key => key.toLowerCase().includes('no')) || answerKeys[1];
            const yesCount = question.answers[yesKey] || 0;
            const noCount = question.answers[noKey] || 0;
            
            questionsCharts[question.question_id] = new Chart(ctx, {
                type: 'doughnut',
                data: {
                    labels: [yesKey, noKey],
                    datasets: [{
                        data: [yesCount, noCount],
                        backgroundColor: [
                            'rgba(40, 167, 69, 0.8)',  // Green for Yes
                            'rgba(220, 53, 69, 0.8)'   // Red for No
                        ],
                        borderColor: [
                            'rgba(40, 167, 69, 1)',
                            'rgba(220, 53, 69, 1)'
                        ],
                        borderWidth: 2
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: true,
                    cutout: '60%',
                    plugins: {
                        legend: {
                            position: 'bottom'
                        },
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    const label = context.label || '';
                                    const value = context.parsed || 0;
                                    const percentage = totalResponses > 0 ? ((value / totalResponses) * 100).toFixed(1) : 0;
                                    return label + ': ' + value + ' (' + percentage + '%)';
                                }
                            }
                        }
                    }
                },
                plugins: [{
                    id: 'dialText',
                    afterDraw: (chart) => {
                        const ctx = chart.ctx;
                        const centerX = chart.chartArea.left + (chart.chartArea.right - chart.chartArea.left) / 2;
                        const centerY = chart.chartArea.top + (chart.chartArea.bottom - chart.chartArea.top) / 2;
                        
                        ctx.save();
                        ctx.font = 'bold 20px Arial';
                        ctx.fillStyle = '#333';
                        ctx.textAlign = 'center';
                        ctx.textBaseline = 'middle';
                        ctx.fillText(totalResponses, centerX, centerY - 10);
                        
                        ctx.font = '14px Arial';
                        ctx.fillText('Total', centerX, centerY + 15);
                        ctx.restore();
                    }
                }]
            });
        } else {
            // Create bar chart for multiple option questions
            const labels = answerKeys;
            const counts = answerKeys.map(key => question.answers[key] || 0);
            
            questionsCharts[question.question_id] = new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: labels,
                    datasets: [{
                        label: 'Responses',
                        data: counts,
                        backgroundColor: 'rgba(44, 95, 141, 0.8)',
                        borderColor: 'rgba(44, 95, 141, 1)',
                        borderWidth: 1
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: true,
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: {
                                stepSize: 1
                            },
                            title: {
                                display: true,
                                text: 'Number of Responses'
                            }
                        },
                        x: {
                            title: {
                                display: true,
                                text: 'Options'
                            },
                            ticks: {
                                maxRotation: 45,
                                minRotation: 45
                            }
                        }
                    },
                    plugins: {
                        legend: {
                            display: false
                        },
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    const value = context.parsed.y || 0;
                                    const percentage = totalResponses > 0 ? ((value / totalResponses) * 100).toFixed(1) : 0;
                                    return 'Responses: ' + value + ' (' + percentage + '%)';
                                }
                            }
                        }
                    }
                }
            });
        }
    });
}

function escapeHtml(text) {
    if (text === null || text === undefined) return '';
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

function initializeAvailabilityDates() {
    const today = new Date();
    const toDate = new Date(today);
    const fromDate = new Date(today);
    fromDate.setDate(fromDate.getDate() - 30); // Default to last 30 days
    
    document.getElementById('date-from').value = fromDate.toISOString().split('T')[0];
    document.getElementById('date-to').value = toDate.toISOString().split('T')[0];
    
    loadAvailabilityData();
}

function updateDateRange() {
    const periodType = document.getElementById('period-type').value;
    const today = new Date();
    const toDate = new Date(today);
    const fromDate = new Date(today);
    
    switch(periodType) {
        case 'daily':
            fromDate.setDate(fromDate.getDate() - 7);
            break;
        case 'weekly':
            fromDate.setDate(fromDate.getDate() - 28); // 4 weeks
            break;
        case 'monthly':
            fromDate.setMonth(fromDate.getMonth() - 6); // 6 months
            break;
        case 'annually':
            fromDate.setFullYear(fromDate.getFullYear() - 1);
            break;
    }
    
    document.getElementById('date-from').value = fromDate.toISOString().split('T')[0];
    document.getElementById('date-to').value = toDate.toISOString().split('T')[0];
    
    loadAvailabilityData();
}

function loadAvailabilityData() {
    const fromDate = document.getElementById('date-from').value;
    const toDate = document.getElementById('date-to').value;
    const periodType = document.getElementById('period-type').value;
    
    if (!fromDate || !toDate) {
        return;
    }
    
    fetch('<?= baseUrl('/api/water-availability-analytics.php') ?>?from=' + fromDate + '&to=' + toDate + '&period=' + periodType)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                renderAvailabilityCharts(data.data);
            } else {
                console.error('Error loading availability data:', data.message);
            }
        })
        .catch(error => {
            console.error('Error loading availability data:', error);
        });
}

function renderAvailabilityCharts(data) {
    // Pie chart for availability
    const pieCtx = document.getElementById('availabilityPieChart');
    if (availabilityPieChart) {
        availabilityPieChart.destroy();
    }
    
    const total = (data.has_water || 0) + (data.intermittent_water || 0) + (data.no_water || 0);
    const hasWaterPercent = total > 0 ? ((data.has_water || 0) / total) * 100 : 0;
    const intermittentPercent = total > 0 ? ((data.intermittent_water || 0) / total) * 100 : 0;
    const noWaterPercent = total > 0 ? ((data.no_water || 0) / total) * 100 : 0;
    
    availabilityPieChart = new Chart(pieCtx, {
        type: 'pie',
        data: {
            labels: ['Has Water', 'Intermittent', 'No Water'],
            datasets: [{
                data: [hasWaterPercent, intermittentPercent, noWaterPercent],
                backgroundColor: [
                    'rgba(40, 167, 69, 0.8)',   // Green
                    'rgba(243, 156, 18, 0.8)',  // Orange
                    'rgba(220, 53, 69, 0.8)'    // Red
                ],
                borderColor: [
                    'rgba(40, 167, 69, 1)',
                    'rgba(243, 156, 18, 1)',
                    'rgba(220, 53, 69, 1)'
                ],
                borderWidth: 2
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: true,
            plugins: {
                legend: {
                    position: 'bottom'
                },
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            const label = context.label || '';
                            const value = context.parsed || 0;
                            let count = 0;
                            if (label === 'Has Water') {
                                count = data.has_water || 0;
                            } else if (label === 'Intermittent') {
                                count = data.intermittent_water || 0;
                            } else if (label === 'No Water') {
                                count = data.no_water || 0;
                            }
                            return label + ': ' + value.toFixed(1) + '% (' + count + ' responses)';
                        }
                    }
                }
            }
        }
    });
    
    // Gauge chart for response rate
    const gaugeCtx = document.getElementById('responseGaugeChart');
    if (responseGaugeChart) {
        responseGaugeChart.destroy();
    }
    
    const responseRate = data.total_registered > 0 ? (data.total_respondents / data.total_registered) * 100 : 0;
    
    // Create a gauge using a doughnut chart
    responseGaugeChart = new Chart(gaugeCtx, {
        type: 'doughnut',
        data: {
            labels: ['Responded', 'Not Responded'],
            datasets: [{
                data: [responseRate, 100 - responseRate],
                backgroundColor: [
                    'rgba(54, 162, 235, 0.8)',
                    'rgba(200, 200, 200, 0.3)'
                ],
                borderWidth: 0
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: true,
            cutout: '75%',
            plugins: {
                legend: {
                    display: false
                },
                tooltip: {
                    enabled: false
                }
            }
        },
        plugins: [{
            id: 'gaugeText',
            afterDraw: (chart) => {
                const ctx = chart.ctx;
                const centerX = chart.chartArea.left + (chart.chartArea.right - chart.chartArea.left) / 2;
                const centerY = chart.chartArea.top + (chart.chartArea.bottom - chart.chartArea.top) / 2;
                
                ctx.save();
                ctx.font = 'bold 24px Arial';
                ctx.fillStyle = '#333';
                ctx.textAlign = 'center';
                ctx.textBaseline = 'middle';
                ctx.fillText(responseRate.toFixed(1) + '%', centerX, centerY - 10);
                
                ctx.font = '16px Arial';
                ctx.fillText(data.total_respondents + ' / ' + data.total_registered, centerX, centerY + 15);
                ctx.restore();
            }
        }]
    });
}
</script>

<style>
.analytics-tabs {
    display: flex;
    gap: 0.5rem;
    margin-bottom: 2rem;
    border-bottom: 2px solid var(--border-color);
}

.tab-button {
    padding: 0.75rem 1.5rem;
    background: none;
    border: none;
    border-bottom: 3px solid transparent;
    cursor: pointer;
    font-size: 1rem;
    color: #666;
    transition: all 0.3s;
}

.tab-button:hover {
    color: var(--primary-color);
}

.tab-button.active {
    color: var(--primary-color);
    border-bottom-color: var(--primary-color);
    font-weight: 600;
}

.analytics-tab-content {
    display: none;
}

.analytics-tab-content.active {
    display: block;
}

.chart-container {
    background-color: white;
    padding: 2rem;
    border-radius: 8px;
    box-shadow: var(--shadow);
    margin-bottom: 2rem;
    min-height: 400px;
}

.questions-charts-container {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(400px, 1fr));
    gap: 2rem;
}

.question-chart-container {
    background-color: white;
    padding: 1.5rem;
    border-radius: 8px;
    box-shadow: var(--shadow);
}

.question-chart-container h3 {
    margin-top: 0;
    margin-bottom: 1rem;
    font-size: 1.1rem;
    color: var(--text-color);
    line-height: 1.4;
}

.question-chart-wrapper {
    position: relative;
    height: 300px;
    margin-bottom: 1rem;
}

.question-chart-stats {
    text-align: center;
    padding-top: 1rem;
    border-top: 1px solid var(--border-color);
    color: #666;
    font-size: 0.9rem;
}

.availability-controls {
    display: flex;
    gap: 1rem;
    margin-bottom: 2rem;
    flex-wrap: wrap;
    align-items: flex-end;
}

.availability-controls .form-group {
    margin: 0;
}

.availability-controls label {
    display: block;
    margin-bottom: 0.5rem;
    font-weight: 600;
}

.availability-controls select,
.availability-controls input[type="date"] {
    padding: 0.5rem;
    border: 1px solid var(--border-color);
    border-radius: 4px;
    font-size: 1rem;
}

.availability-charts {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
    gap: 2rem;
}

.availability-charts .chart-container {
    min-height: 300px;
}

.availability-charts h3 {
    margin-top: 0;
    margin-bottom: 1rem;
    text-align: center;
}

.export-controls {
    display: flex;
    gap: 1rem;
    margin-bottom: 2rem;
    flex-wrap: wrap;
    align-items: flex-end;
}

.export-controls .form-group {
    margin: 0;
}

.export-controls label {
    display: block;
    margin-bottom: 0.5rem;
    font-weight: 600;
}

.export-controls input[type="date"] {
    padding: 0.5rem;
    border: 1px solid var(--border-color);
    border-radius: 4px;
    font-size: 1rem;
}

.export-table-container {
    background-color: white;
    padding: 1rem;
    border-radius: 8px;
    box-shadow: var(--shadow);
    overflow-x: auto;
}

.export-data-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 0.9rem;
}

.export-data-table thead {
    background-color: var(--bg-color);
}

.export-data-table th {
    padding: 0.75rem;
    text-align: left;
    font-weight: 600;
    border-bottom: 2px solid var(--border-color);
    position: sticky;
    top: 0;
    background-color: var(--bg-color);
    z-index: 10;
}

.export-data-table td {
    padding: 0.75rem;
    border-bottom: 1px solid var(--border-color);
}

.export-data-table tbody tr:hover {
    background-color: #f8f9fa;
}

.export-data-table tbody tr:last-child td {
    border-bottom: none;
}
</style>

<script>
function initializeExportDates() {
    const today = new Date();
    const toDate = new Date(today);
    const fromDate = new Date(today);
    fromDate.setDate(fromDate.getDate() - 30); // Default to last 30 days
    
    document.getElementById('export-date-from').value = fromDate.toISOString().split('T')[0];
    document.getElementById('export-date-to').value = toDate.toISOString().split('T')[0];
}

function loadExportData() {
    const fromDate = document.getElementById('export-date-from').value;
    const toDate = document.getElementById('export-date-to').value;
    
    if (!fromDate || !toDate) {
        return;
    }
    
    const tbody = document.getElementById('export-data-tbody');
    tbody.innerHTML = '<tr><td colspan="5" style="text-align: center; padding: 2rem; color: #666;">Loading...</td></tr>';
    
    fetch('<?= baseUrl('/api/water-export-data.php') ?>?from=' + fromDate + '&to=' + toDate)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                exportData = data.data;
                renderExportTable(data.data);
            } else {
                tbody.innerHTML = '<tr><td colspan="5" style="text-align: center; padding: 2rem; color: #d32f2f;">Error loading data</td></tr>';
            }
        })
        .catch(error => {
            console.error('Error loading export data:', error);
            tbody.innerHTML = '<tr><td colspan="5" style="text-align: center; padding: 2rem; color: #d32f2f;">Error loading data</td></tr>';
        });
}

function renderExportTable(data) {
    const tbody = document.getElementById('export-data-tbody');
    
    if (data.length === 0) {
        tbody.innerHTML = '<tr><td colspan="5" style="text-align: center; padding: 2rem; color: #666;">No data found for the selected date range</td></tr>';
        return;
    }
    
    let html = '';
    data.forEach(row => {
        const date = new Date(row.report_date + 'T00:00:00').toLocaleDateString();
        const address = row.address || 'N/A';
        const latitude = row.latitude !== null ? parseFloat(row.latitude).toFixed(8) : 'N/A';
        const longitude = row.longitude !== null ? parseFloat(row.longitude).toFixed(8) : 'N/A';
        let waterAvailable = 'No';
        if (row.has_water == 1) {
            waterAvailable = 'Yes';
        } else if (row.has_water == 2) {
            waterAvailable = 'Intermittent';
        }
        
        html += `
            <tr>
                <td>${escapeHtml(date)}</td>
                <td>${escapeHtml(address)}</td>
                <td>${escapeHtml(latitude)}</td>
                <td>${escapeHtml(longitude)}</td>
                <td>${escapeHtml(waterAvailable)}</td>
            </tr>
        `;
    });
    
    tbody.innerHTML = html;
}

function exportToCSV() {
    if (exportData.length === 0) {
        alert('No data to export. Please select a date range and wait for data to load.');
        return;
    }
    
    // Create CSV content
    let csv = 'Date,Address,Latitude,Longitude,Water Available\n';
    
    exportData.forEach(row => {
        const date = new Date(row.report_date + 'T00:00:00').toLocaleDateString();
        const address = (row.address || 'N/A').replace(/"/g, '""'); // Escape quotes
        const latitude = row.latitude !== null ? parseFloat(row.latitude).toFixed(8) : 'N/A';
        const longitude = row.longitude !== null ? parseFloat(row.longitude).toFixed(8) : 'N/A';
        let waterAvailable = 'No';
        if (row.has_water == 1) {
            waterAvailable = 'Yes';
        } else if (row.has_water == 2) {
            waterAvailable = 'Intermittent';
        }
        
        csv += `"${date}","${address}","${latitude}","${longitude}","${waterAvailable}"\n`;
    });
    
    // Create blob and download
    const blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
    const link = document.createElement('a');
    const url = URL.createObjectURL(blob);
    
    const fromDate = document.getElementById('export-date-from').value;
    const toDate = document.getElementById('export-date-to').value;
    const filename = `water-availability-${fromDate}-to-${toDate}.csv`;
    
    link.setAttribute('href', url);
    link.setAttribute('download', filename);
    link.style.visibility = 'hidden';
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
}

function escapeHtml(text) {
    if (text === null || text === undefined) return '';
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}
</script>

<?php 
$hideAdverts = true;
include 'includes/footer.php'; 
?>

