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
            <button class="tab-button active" onclick="switchTab('questions', this)">Water Questions</button>
            <button class="tab-button" onclick="switchTab('availability', this)">Water Availability</button>
            <button class="tab-button" onclick="switchTab('deliveries', this)">Water Deliveries</button>
            <button class="tab-button" onclick="switchTab('tankers', this)">Tanker Reports</button>
            <button class="tab-button" onclick="switchTab('export', this)">Export Data</button>
        </div>
        
        <!-- Water Questions Tab -->
        <div id="questions-tab" class="analytics-tab-content active">
            <h2>Water Questions Responses</h2>
            
            <div class="questions-controls">
                <div class="form-group">
                    <label for="questions-date-from">From Date:</label>
                    <input type="date" id="questions-date-from" onchange="loadQuestionsData()">
                </div>
                
                <div class="form-group">
                    <label for="questions-date-to">To Date:</label>
                    <input type="date" id="questions-date-to" onchange="loadQuestionsData()">
                </div>
            </div>
            
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
        
        <!-- Tanker Reports Tab -->
        <div id="tankers-tab" class="analytics-tab-content">
            <h2>Tanker Reports</h2>
            
            <div class="tanker-filters" style="background: #f5f5f5; padding: 20px; border-radius: 4px; margin-bottom: 20px;">
                <div style="display: flex; gap: 1rem; align-items: flex-end; flex-wrap: wrap;">
                    <div class="form-group" style="margin: 0;">
                        <label for="tankers-date-from">From Date:</label>
                        <input type="date" id="tankers-date-from" value="<?= date('Y-m-d', strtotime('-30 days')) ?>">
                    </div>
                    
                    <div class="form-group" style="margin: 0;">
                        <label for="tankers-date-to">To Date:</label>
                        <input type="date" id="tankers-date-to" value="<?= date('Y-m-d') ?>">
                    </div>
                    
                    <div class="form-group" style="margin: 0;">
                        <button type="button" class="btn btn-primary" onclick="loadTankerReports()">Filter</button>
                        <button type="button" class="btn btn-secondary" onclick="resetTankerFilters()">Reset</button>
                    </div>
                </div>
            </div>
            
            <!-- Map -->
            <div id="tanker-map" style="width: 100%; height: 400px; border: 1px solid #ddd; border-radius: 4px; margin-bottom: 20px;"></div>
            
            <!-- Reports List -->
            <div class="tanker-reports-list">
                <h3>Tanker Reports</h3>
                <div id="tanker-reports-container">
                    <p style="color: #666;">Loading reports...</p>
                </div>
            </div>
        </div>
        
        <!-- Water Deliveries Tab -->
        <div id="deliveries-tab" class="analytics-tab-content">
            <h2>Water Deliveries Analytics</h2>
            
            <div class="deliveries-controls">
                <div class="form-group">
                    <label for="deliveries-date-from">From Date:</label>
                    <input type="date" id="deliveries-date-from" onchange="loadDeliveriesData()">
                </div>
                
                <div class="form-group">
                    <label for="deliveries-date-to">To Date:</label>
                    <input type="date" id="deliveries-date-to" onchange="loadDeliveriesData()">
                </div>
            </div>
            
            <div id="deliveries-totals" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin-bottom: 2rem;">
                <div style="background: white; padding: 15px; border-radius: 4px; border-left: 4px solid #2c5f8d;">
                    <p style="margin: 0; color: #666; font-size: 0.9rem;">Total Litres</p>
                    <p style="margin: 0; font-size: 1.8rem; font-weight: bold; color: #2c5f8d;" id="total-litres">0.00 L</p>
                </div>
                <div style="background: white; padding: 15px; border-radius: 4px; border-left: 4px solid #2c5f8d;">
                    <p style="margin: 0; color: #666; font-size: 0.9rem;">Total Price</p>
                    <p style="margin: 0; font-size: 1.8rem; font-weight: bold; color: #2c5f8d;" id="total-price">R 0.00</p>
                </div>
            </div>
            
            <div class="deliveries-charts" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(400px, 1fr)); gap: 20px;">
                <div class="chart-container" style="background: white; padding: 20px; border-radius: 4px; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
                    <h3>Total Litres by Company</h3>
                    <canvas id="deliveriesLitresChart"></canvas>
                </div>
                
                <div class="chart-container" style="background: white; padding: 20px; border-radius: 4px; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
                    <h3>Total Price by Company</h3>
                    <canvas id="deliveriesPriceChart"></canvas>
                </div>
            </div>
            
            <!-- Delivery Records List -->
            <div class="deliveries-records" style="margin-top: 2rem;">
                <h3>Delivery Records</h3>
                <div id="deliveries-records-container" style="background: white; padding: 1rem; border-radius: 4px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); overflow-x: auto;">
                    <p style="color: #666; text-align: center; padding: 2rem;">Select date range to view records</p>
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
                            <th>Name</th>
                            <th>Telephone</th>
                            <th>Email</th>
                            <th>Address</th>
                            <th>Latitude</th>
                            <th>Longitude</th>
                            <th>Water Available</th>
                        </tr>
                    </thead>
                    <tbody id="export-data-tbody">
                        <tr>
                            <td colspan="8" style="text-align: center; padding: 2rem; color: #666;">
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
    initializeQuestionsDates();
    loadQuestionsData();
    initializeAvailabilityDates();
    initializeExportDates();
    initializeDeliveriesDates();
});

function switchTab(tabName, buttonElement) {
    // Hide all tabs
    document.querySelectorAll('.analytics-tab-content').forEach(tab => {
        tab.classList.remove('active');
    });
    document.querySelectorAll('.analytics-tabs .tab-button').forEach(btn => {
        btn.classList.remove('active');
    });
    
    // Show selected tab
    const selectedTab = document.getElementById(tabName + '-tab');
    if (selectedTab) {
        selectedTab.classList.add('active');
    }
    
    // Activate clicked button
    if (buttonElement) {
        buttonElement.classList.add('active');
    } else {
        // Fallback: find button by tab name
        const buttons = document.querySelectorAll('.analytics-tabs .tab-button');
        buttons.forEach(btn => {
            if (btn.getAttribute('onclick') && btn.getAttribute('onclick').includes("'" + tabName + "'")) {
                btn.classList.add('active');
            }
        });
    }
    
    // Load data for the tab
    if (tabName === 'questions') {
        loadQuestionsData();
    } else if (tabName === 'availability') {
        loadAvailabilityData();
    } else if (tabName === 'deliveries') {
        // Wait a bit for the tab to be visible before loading charts
        setTimeout(() => {
            loadDeliveriesData();
        }, 100);
    } else if (tabName === 'tankers') {
        // Wait a bit for the tab to be visible before loading map
        setTimeout(() => {
            loadTankerReports();
        }, 100);
    } else if (tabName === 'export') {
        loadExportData();
    }
}

function initializeQuestionsDates() {
    const today = new Date();
    const toDate = new Date(today);
    const fromDate = new Date(today);
    fromDate.setDate(fromDate.getDate() - 30); // Default to last 30 days
    
    document.getElementById('questions-date-from').value = fromDate.toISOString().split('T')[0];
    document.getElementById('questions-date-to').value = toDate.toISOString().split('T')[0];
}

function loadQuestionsData() {
    const fromDate = document.getElementById('questions-date-from').value;
    const toDate = document.getElementById('questions-date-to').value;
    
    if (!fromDate || !toDate) {
        return;
    }
    
    fetch('<?= baseUrl('/api/water-questions-analytics.php') ?>?from=' + fromDate + '&to=' + toDate)
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
    overflow-x: auto;
    -webkit-overflow-scrolling: touch;
    scrollbar-width: thin;
}

.analytics-tabs::-webkit-scrollbar {
    height: 6px;
}

.analytics-tabs::-webkit-scrollbar-track {
    background: #f1f1f1;
}

.analytics-tabs::-webkit-scrollbar-thumb {
    background: #888;
    border-radius: 3px;
}

.analytics-tabs::-webkit-scrollbar-thumb:hover {
    background: #555;
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
    margin-bottom: -2px;
    white-space: nowrap;
    flex-shrink: 0;
}

.tab-button:hover {
    color: var(--primary-color);
}

.tab-button.active {
    color: var(--primary-color);
    border-bottom-color: var(--primary-color);
    font-weight: 600;
}

/* Mobile optimizations */
@media (max-width: 768px) {
    .analytics-tabs {
        gap: 0.25rem;
        margin-bottom: 1.5rem;
        padding-bottom: 0.5rem;
    }
    
    .tab-button {
        padding: 0.6rem 1rem;
        font-size: 0.9rem;
    }
}

@media (max-width: 480px) {
    .analytics-tabs {
        gap: 0.2rem;
    }
    
    .tab-button {
        padding: 0.5rem 0.6rem;
        font-size: 0.85rem;
    }
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

.questions-controls {
    display: flex;
    gap: 1rem;
    margin-bottom: 2rem;
    flex-wrap: wrap;
    align-items: flex-end;
}

.questions-controls .form-group {
    margin: 0;
}

.questions-controls label {
    display: block;
    margin-bottom: 0.5rem;
    font-weight: 600;
}

.questions-controls input[type="date"] {
    padding: 0.5rem;
    border: 1px solid var(--border-color);
    border-radius: 4px;
    font-size: 1rem;
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

.deliveries-controls {
    display: flex;
    gap: 1rem;
    margin-bottom: 2rem;
    flex-wrap: wrap;
    align-items: flex-end;
}

.deliveries-controls .form-group {
    margin: 0;
}

.deliveries-controls label {
    display: block;
    margin-bottom: 0.5rem;
    font-weight: 600;
}

.deliveries-controls input[type="date"] {
    padding: 0.5rem;
    border: 1px solid var(--border-color);
    border-radius: 4px;
    font-size: 1rem;
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

.deliveries-records-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 0.9rem;
}

.deliveries-records-table thead {
    background-color: var(--bg-color);
    position: sticky;
    top: 0;
    z-index: 10;
}

.deliveries-records-table th {
    padding: 0.75rem;
    text-align: left;
    font-weight: 600;
    border-bottom: 2px solid var(--border-color);
    background-color: var(--bg-color);
}

.deliveries-records-table td {
    padding: 0.75rem;
    border-bottom: 1px solid var(--border-color);
}

.deliveries-records-table tbody tr:hover {
    background-color: #f8f9fa !important;
}

.deliveries-records-table tbody tr:last-child td {
    border-bottom: none;
}

/* Mobile responsive for deliveries records table */
@media (max-width: 768px) {
    .deliveries-records-table {
        font-size: 0.8rem;
    }
    
    .deliveries-records-table th,
    .deliveries-records-table td {
        padding: 0.5rem;
    }
    
    .deliveries-records-table th:nth-child(4),
    .deliveries-records-table td:nth-child(4),
    .deliveries-records-table th:nth-child(5),
    .deliveries-records-table td:nth-child(5) {
        max-width: 150px;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
    }
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
    tbody.innerHTML = '<tr><td colspan="8" style="text-align: center; padding: 2rem; color: #666;">Loading...</td></tr>';
    
    fetch('<?= baseUrl('/api/water-export-data.php') ?>?from=' + fromDate + '&to=' + toDate)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                exportData = data.data;
                renderExportTable(data.data);
            } else {
                tbody.innerHTML = '<tr><td colspan="8" style="text-align: center; padding: 2rem; color: #d32f2f;">Error loading data</td></tr>';
            }
        })
        .catch(error => {
            console.error('Error loading export data:', error);
            tbody.innerHTML = '<tr><td colspan="8" style="text-align: center; padding: 2rem; color: #d32f2f;">Error loading data</td></tr>';
        });
}

function renderExportTable(data) {
    const tbody = document.getElementById('export-data-tbody');
    
    if (data.length === 0) {
        tbody.innerHTML = '<tr><td colspan="8" style="text-align: center; padding: 2rem; color: #666;">No data found for the selected date range</td></tr>';
        return;
    }
    
    let html = '';
    data.forEach(row => {
        const date = new Date(row.report_date + 'T00:00:00').toLocaleDateString();
        const fullName = row.full_name || 'N/A';
        const telephone = row.telephone || 'N/A';
        const email = row.email || 'N/A';
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
                <td>${escapeHtml(fullName)}</td>
                <td>${escapeHtml(telephone)}</td>
                <td>${escapeHtml(email)}</td>
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
    let csv = 'Date,Name,Telephone,Email,Address,Latitude,Longitude,Water Available\n';
    
    exportData.forEach(row => {
        const date = new Date(row.report_date + 'T00:00:00').toLocaleDateString();
        const fullName = (row.full_name || 'N/A').replace(/"/g, '""'); // Escape quotes
        const telephone = (row.telephone || 'N/A').replace(/"/g, '""'); // Escape quotes
        const email = (row.email || 'N/A').replace(/"/g, '""'); // Escape quotes
        const address = (row.address || 'N/A').replace(/"/g, '""'); // Escape quotes
        const latitude = row.latitude !== null ? parseFloat(row.latitude).toFixed(8) : 'N/A';
        const longitude = row.longitude !== null ? parseFloat(row.longitude).toFixed(8) : 'N/A';
        let waterAvailable = 'No';
        if (row.has_water == 1) {
            waterAvailable = 'Yes';
        } else if (row.has_water == 2) {
            waterAvailable = 'Intermittent';
        }
        
        csv += `"${date}","${fullName}","${telephone}","${email}","${address}","${latitude}","${longitude}","${waterAvailable}"\n`;
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

// Water Deliveries Analytics
let deliveriesLitresChart = null;
let deliveriesPriceChart = null;

function initializeDeliveriesDates() {
    const today = new Date();
    const oneYearAgo = new Date();
    oneYearAgo.setFullYear(today.getFullYear() - 1);
    
    document.getElementById('deliveries-date-from').value = oneYearAgo.toISOString().split('T')[0];
    document.getElementById('deliveries-date-to').value = today.toISOString().split('T')[0];
}

function loadDeliveriesData() {
    const fromDate = document.getElementById('deliveries-date-from');
    const toDate = document.getElementById('deliveries-date-to');
    
    if (!fromDate || !toDate) {
        console.error('Date inputs not found');
        return;
    }
    
    const fromDateValue = fromDate.value;
    const toDateValue = toDate.value;
    
    if (!fromDateValue || !toDateValue) {
        initializeDeliveriesDates();
        setTimeout(loadDeliveriesData, 100);
        return;
    }
    
    fetch(`<?= baseUrl('/api/water-deliveries-analytics.php') ?>?from=${fromDateValue}&to=${toDateValue}`)
        .then(response => {
            if (!response.ok) {
                throw new Error('Network response was not ok');
            }
            return response.json();
        })
        .then(data => {
            if (data.error) {
                console.error('Error loading deliveries data:', data.error);
                alert('Error loading data: ' + data.error);
                return;
            }
            
            // Update totals
            const totalLitresEl = document.getElementById('total-litres');
            const totalPriceEl = document.getElementById('total-price');
            if (totalLitresEl) {
                totalLitresEl.textContent = parseFloat(data.total_litres || 0).toLocaleString('en-ZA', {minimumFractionDigits: 2, maximumFractionDigits: 2}) + ' L';
            }
            if (totalPriceEl) {
                totalPriceEl.textContent = 'R ' + parseFloat(data.total_price || 0).toLocaleString('en-ZA', {minimumFractionDigits: 2, maximumFractionDigits: 2});
            }
            
            // Prepare chart data
            const companies = data.companies || [];
            if (companies.length === 0) {
                console.log('No delivery data found for the selected date range');
                // Clear charts if no data
                if (deliveriesLitresChart) {
                    deliveriesLitresChart.destroy();
                    deliveriesLitresChart = null;
                }
                if (deliveriesPriceChart) {
                    deliveriesPriceChart.destroy();
                    deliveriesPriceChart = null;
                }
                // Clear records table
                renderDeliveriesRecords([]);
                return;
            }
            
            const companyNames = companies.map(c => c.company_name);
            const litresData = companies.map(c => parseFloat(c.total_litres || 0));
            const priceData = companies.map(c => parseFloat(c.total_price || 0));
            
            // Destroy existing charts
            if (deliveriesLitresChart) {
                deliveriesLitresChart.destroy();
                deliveriesLitresChart = null;
            }
            if (deliveriesPriceChart) {
                deliveriesPriceChart.destroy();
                deliveriesPriceChart = null;
            }
            
            // Create Litres chart
            const litresCanvas = document.getElementById('deliveriesLitresChart');
            if (!litresCanvas) {
                console.error('deliveriesLitresChart canvas element not found');
                return;
            }
            const litresCtx = litresCanvas.getContext('2d');
            deliveriesLitresChart = new Chart(litresCtx, {
                type: 'bar',
                data: {
                    labels: companyNames,
                    datasets: [{
                        label: 'Total Litres',
                        data: litresData,
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
                                callback: function(value) {
                                    return value.toLocaleString('en-ZA') + ' L';
                                }
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
                                    return context.parsed.y.toLocaleString('en-ZA', {minimumFractionDigits: 2, maximumFractionDigits: 2}) + ' L';
                                }
                            }
                        }
                    }
                }
            });
            
            // Create Price chart
            const priceCanvas = document.getElementById('deliveriesPriceChart');
            if (!priceCanvas) {
                console.error('deliveriesPriceChart canvas element not found');
                return;
            }
            const priceCtx = priceCanvas.getContext('2d');
            deliveriesPriceChart = new Chart(priceCtx, {
                type: 'bar',
                data: {
                    labels: companyNames,
                    datasets: [{
                        label: 'Total Price',
                        data: priceData,
                        backgroundColor: 'rgba(76, 175, 80, 0.8)',
                        borderColor: 'rgba(76, 175, 80, 1)',
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
                                callback: function(value) {
                                    return 'R ' + value.toLocaleString('en-ZA', {minimumFractionDigits: 2, maximumFractionDigits: 2});
                                }
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
                                    return 'R ' + context.parsed.y.toLocaleString('en-ZA', {minimumFractionDigits: 2, maximumFractionDigits: 2});
                                }
                            }
                        }
                    }
                }
            });
            
            // Render delivery records table
            renderDeliveriesRecords(data.records || []);
        })
        .catch(error => {
            console.error('Error loading deliveries data:', error);
            alert('Error loading deliveries data. Please check the console for details.');
        });
}

function renderDeliveriesRecords(records) {
    const container = document.getElementById('deliveries-records-container');
    if (!container) {
        return;
    }
    
    if (records.length === 0) {
        container.innerHTML = '<p style="color: #666; text-align: center; padding: 2rem;">No delivery records found for the selected date range</p>';
        return;
    }
    
    let html = '<table class="deliveries-records-table" style="width: 100%; border-collapse: collapse; font-size: 0.9rem;">';
    html += '<thead style="background: var(--bg-color);">';
    html += '<tr>';
    html += '<th style="padding: 0.75rem; text-align: left; border-bottom: 2px solid var(--border-color); font-weight: 600;">Delivery Date</th>';
    html += '<th style="padding: 0.75rem; text-align: left; border-bottom: 2px solid var(--border-color); font-weight: 600;">Name</th>';
    html += '<th style="padding: 0.75rem; text-align: left; border-bottom: 2px solid var(--border-color); font-weight: 600;">Surname</th>';
    html += '<th style="padding: 0.75rem; text-align: left; border-bottom: 2px solid var(--border-color); font-weight: 600;">Telephone</th>';
    html += '<th style="padding: 0.75rem; text-align: left; border-bottom: 2px solid var(--border-color); font-weight: 600;">Address</th>';
    html += '<th style="padding: 0.75rem; text-align: right; border-bottom: 2px solid var(--border-color); font-weight: 600;">Litres</th>';
    html += '<th style="padding: 0.75rem; text-align: right; border-bottom: 2px solid var(--border-color); font-weight: 600;">Price</th>';
    html += '</tr>';
    html += '</thead>';
    html += '<tbody>';
    
    records.forEach((record, index) => {
        const rowStyle = index % 2 === 0 ? 'background: #fff;' : 'background: #f9f9f9;';
        const deliveryDate = new Date(record.date_delivered + 'T00:00:00').toLocaleDateString('en-ZA');
        const name = escapeHtml(record.name || 'N/A');
        const surname = escapeHtml(record.surname || 'N/A');
        const telephone = escapeHtml(record.telephone || 'N/A');
        const address = escapeHtml(record.address || 'Address not provided');
        const litres = parseFloat(record.litres_delivered || 0).toLocaleString('en-ZA', {minimumFractionDigits: 2, maximumFractionDigits: 2});
        const price = 'R ' + parseFloat(record.price || 0).toLocaleString('en-ZA', {minimumFractionDigits: 2, maximumFractionDigits: 2});
        
        html += `<tr style="${rowStyle}">`;
        html += `<td style="padding: 0.75rem; border-bottom: 1px solid var(--border-color);">${deliveryDate}</td>`;
        html += `<td style="padding: 0.75rem; border-bottom: 1px solid var(--border-color);">${name}</td>`;
        html += `<td style="padding: 0.75rem; border-bottom: 1px solid var(--border-color);">${surname}</td>`;
        html += `<td style="padding: 0.75rem; border-bottom: 1px solid var(--border-color);">${telephone}</td>`;
        html += `<td style="padding: 0.75rem; border-bottom: 1px solid var(--border-color);">${address}</td>`;
        html += `<td style="padding: 0.75rem; border-bottom: 1px solid var(--border-color); text-align: right;">${litres} L</td>`;
        html += `<td style="padding: 0.75rem; border-bottom: 1px solid var(--border-color); text-align: right;">${price}</td>`;
        html += '</tr>';
    });
    
    html += '</tbody>';
    html += '</table>';
    
    container.innerHTML = html;
}

// Tanker Reports Functions
let tankerMap = null;
let tankerMarkers = [];

function loadTankerReports() {
    const fromDate = document.getElementById('tankers-date-from').value;
    const toDate = document.getElementById('tankers-date-to').value;
    
    if (!fromDate || !toDate) {
        alert('Please select both from and to dates.');
        return;
    }
    
    const apiUrl = '<?= baseUrl('/api/tanker-reports.php') ?>?from_date=' + encodeURIComponent(fromDate) + '&to_date=' + encodeURIComponent(toDate);
    
    fetch(apiUrl)
        .then(response => response.json())
        .then(data => {
            if (data.success && data.reports) {
                displayTankerReportsList(data.reports);
                displayTankerMapMarkers(data.reports);
            } else {
                document.getElementById('tanker-reports-container').innerHTML = '<p>No reports found for the selected date range.</p>';
            }
        })
        .catch(error => {
            console.error('Error loading tanker reports:', error);
            document.getElementById('tanker-reports-container').innerHTML = '<p style="color: red;">Error loading reports. Please try again.</p>';
        });
}

function resetTankerFilters() {
    document.getElementById('tankers-date-from').value = '<?= date('Y-m-d', strtotime('-30 days')) ?>';
    document.getElementById('tankers-date-to').value = '<?= date('Y-m-d') ?>';
    loadTankerReports();
}

function displayTankerReportsList(reports) {
    const container = document.getElementById('tanker-reports-container');
    
    if (reports.length === 0) {
        container.innerHTML = '<p>No reports found for the selected date range.</p>';
        return;
    }
    
    let html = '<div style="background: white; border-radius: 4px; overflow: hidden;">';
    html += '<table style="width: 100%; border-collapse: collapse;">';
    html += '<thead style="background: #f5f5f5;">';
    html += '<tr>';
    html += '<th style="padding: 12px; text-align: left; border-bottom: 2px solid #ddd;">Photo</th>';
    html += '<th style="padding: 12px; text-align: left; border-bottom: 2px solid #ddd;">Registration</th>';
    html += '<th style="padding: 12px; text-align: left; border-bottom: 2px solid #ddd;">Date</th>';
    html += '<th style="padding: 12px; text-align: left; border-bottom: 2px solid #ddd;">Device</th>';
    html += '<th style="padding: 12px; text-align: left; border-bottom: 2px solid #ddd;">Location</th>';
    html += '<th style="padding: 12px; text-align: left; border-bottom: 2px solid #ddd;">Actions</th>';
    html += '</tr>';
    html += '</thead>';
    html += '<tbody>';
    
    reports.forEach(function(report, index) {
        const photoUrl = report.photo_path ? '<?= baseUrl('/') ?>' + report.photo_path : '';
        const reportDate = new Date(report.reported_at).toLocaleString();
        const rowStyle = index % 2 === 0 ? 'background: #fff;' : 'background: #f9f9f9;';
        
        html += `<tr style="${rowStyle}">`;
        html += '<td style="padding: 10px; border-bottom: 1px solid #eee;">';
        if (photoUrl) {
            html += `<img src="${photoUrl}" alt="Tanker Photo" style="width: 80px; height: 80px; object-fit: cover; border-radius: 4px; cursor: pointer; border: 1px solid #ddd;" onclick="showTankerReportDetails(${report.report_id})">`;
        } else {
            html += '<div style="width: 80px; height: 80px; background: #f5f5f5; border-radius: 4px; display: flex; align-items: center; justify-content: center; color: #999; font-size: 0.8rem;">No Photo</div>';
        }
        html += '</td>';
        html += `<td style="padding: 10px; border-bottom: 1px solid #eee; font-weight: bold;">${escapeHtml(report.registration_number)}</td>`;
        html += `<td style="padding: 10px; border-bottom: 1px solid #eee;">${reportDate}</td>`;
        html += `<td style="padding: 10px; border-bottom: 1px solid #eee;">${escapeHtml(report.device_type)}</td>`;
        html += `<td style="padding: 10px; border-bottom: 1px solid #eee; font-size: 0.9rem; color: #666;">${parseFloat(report.latitude).toFixed(6)}, ${parseFloat(report.longitude).toFixed(6)}</td>`;
        html += '<td style="padding: 10px; border-bottom: 1px solid #eee;">';
        html += `<button type="button" class="btn btn-secondary btn-sm" onclick="showTankerReportDetails(${report.report_id})" style="padding: 6px 12px; font-size: 0.9rem;">View Details</button>`;
        html += '</td>';
        html += '</tr>';
    });
    
    html += '</tbody>';
    html += '</table>';
    html += '</div>';
    
    container.innerHTML = html;
}

function displayTankerMapMarkers(reports) {
    // Initialize map if not already done
    if (!tankerMap) {
        tankerMap = L.map('tanker-map').setView([-33.7, 26.7], 6);
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: '© OpenStreetMap contributors',
            maxZoom: 19
        }).addTo(tankerMap);
    }
    
    // Clear existing markers
    tankerMarkers.forEach(marker => tankerMap.removeLayer(marker));
    tankerMarkers = [];
    
    if (reports.length === 0) {
        return;
    }
    
    const bounds = [];
    
    reports.forEach(function(report) {
        if (report.latitude && report.longitude) {
            const lat = parseFloat(report.latitude);
            const lng = parseFloat(report.longitude);
            
            const icon = L.divIcon({
                className: 'tanker-marker',
                html: '<div style="background: #e74c3c; color: white; border-radius: 50%; width: 30px; height: 30px; display: flex; align-items: center; justify-content: center; font-weight: bold; border: 2px solid white; box-shadow: 0 2px 4px rgba(0,0,0,0.3);">T</div>',
                iconSize: [30, 30],
                iconAnchor: [15, 15]
            });
            
            const marker = L.marker([lat, lng], {icon: icon}).addTo(tankerMap);
            
            const photoUrl = report.photo_path ? '<?= baseUrl('/') ?>' + report.photo_path : '';
            const reportDate = new Date(report.reported_at).toLocaleString();
            
            let popupContent = `
                <div style="min-width: 200px;">
                    <h3 style="margin: 0 0 10px 0;">${escapeHtml(report.registration_number)}</h3>
                    ${photoUrl ? `<img src="${photoUrl}" alt="Tanker Photo" style="width: 100%; max-height: 150px; object-fit: cover; border-radius: 4px; margin-bottom: 10px; cursor: pointer;" onclick="showTankerReportDetails(${report.report_id})">` : ''}
                    <p style="margin: 5px 0; font-size: 0.9rem;"><strong>Date:</strong> ${reportDate}</p>
                    <p style="margin: 5px 0; font-size: 0.9rem;"><strong>Device:</strong> ${escapeHtml(report.device_type)}</p>
                    ${report.address ? `<p style="margin: 5px 0; font-size: 0.9rem;"><strong>Address:</strong> ${escapeHtml(report.address)}</p>` : ''}
                    <button type="button" class="btn btn-primary btn-sm" onclick="showTankerReportDetails(${report.report_id})" style="margin-top: 10px; width: 100%; padding: 6px;">View Full Details</button>
                </div>
            `;
            
            marker.bindPopup(popupContent);
            tankerMarkers.push(marker);
            bounds.push([lat, lng]);
        }
    });
    
    if (bounds.length > 0) {
        tankerMap.fitBounds(bounds, {padding: [50, 50]});
    }
}

function showTankerReportDetails(reportId) {
    fetch('<?= baseUrl('/api/tanker-reports.php') ?>?report_id=' + reportId)
        .then(response => response.json())
        .then(data => {
            if (data.success && data.report) {
                const report = data.report;
                const photoUrl = report.photo_path ? '<?= baseUrl('/') ?>' + report.photo_path : '';
                const reportDate = new Date(report.reported_at).toLocaleString();
                
                let modalContent = `
                    <div style="max-width: 600px; margin: 0 auto;">
                        <h2 style="margin-top: 0;">Tanker Report Details</h2>
                        ${photoUrl ? `
                            <div style="margin-bottom: 20px;">
                                <img src="${photoUrl}" alt="Tanker Photo" style="width: 100%; max-height: 400px; object-fit: contain; border-radius: 4px; border: 1px solid #ddd;">
                            </div>
                        ` : ''}
                        <div style="background: #f5f5f5; padding: 15px; border-radius: 4px;">
                            <p style="margin: 5px 0;"><strong>Registration Number:</strong> ${escapeHtml(report.registration_number)}</p>
                            <p style="margin: 5px 0;"><strong>Reported At:</strong> ${reportDate}</p>
                            <p style="margin: 5px 0;"><strong>Reported By:</strong> ${escapeHtml(report.reported_by_name)}</p>
                            <p style="margin: 5px 0;"><strong>Device Type:</strong> ${escapeHtml(report.device_type)}</p>
                            ${report.address ? `<p style="margin: 5px 0;"><strong>Address:</strong> ${escapeHtml(report.address)}</p>` : ''}
                            <p style="margin: 5px 0;"><strong>Latitude:</strong> ${parseFloat(report.latitude).toFixed(8)}</p>
                            <p style="margin: 5px 0;"><strong>Longitude:</strong> ${parseFloat(report.longitude).toFixed(8)}</p>
                        </div>
                    </div>
                `;
                
                showTankerModal('Tanker Report Details', modalContent);
            }
        })
        .catch(error => {
            console.error('Error loading report details:', error);
            alert('Error loading report details. Please try again.');
        });
}

function showTankerModal(title, content) {
    const existingModal = document.getElementById('tanker-modal');
    if (existingModal) {
        existingModal.remove();
    }
    
    const modal = document.createElement('div');
    modal.id = 'tanker-modal';
    modal.style.cssText = 'position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 10000; display: flex; align-items: center; justify-content: center; padding: 20px;';
    
    const modalContent = document.createElement('div');
    modalContent.style.cssText = 'background: white; border-radius: 8px; padding: 20px; max-width: 90%; max-height: 90vh; overflow-y: auto; position: relative;';
    
    const closeBtn = document.createElement('button');
    closeBtn.innerHTML = '&times;';
    closeBtn.setAttribute('type', 'button');
    closeBtn.setAttribute('aria-label', 'Close');
    closeBtn.style.cssText = 'position: absolute; top: 10px; right: 10px; background: rgba(255,255,255,0.9); border: 2px solid #ddd; border-radius: 50%; font-size: 2rem; cursor: pointer; color: #666; width: 44px; height: 44px; line-height: 40px; text-align: center; z-index: 10001; padding: 0; min-width: 44px; min-height: 44px; touch-action: manipulation;';
    
    // Close function
    function closeModal() {
        modal.remove();
    }
    
    // Add multiple event listeners for better mobile support
    closeBtn.addEventListener('click', function(e) {
        e.preventDefault();
        e.stopPropagation();
        closeModal();
    });
    
    closeBtn.addEventListener('touchend', function(e) {
        e.preventDefault();
        e.stopPropagation();
        closeModal();
    });
    
    // Add hover effect
    closeBtn.addEventListener('mouseenter', function() {
        this.style.backgroundColor = '#f0f0f0';
        this.style.color = '#333';
    });
    
    closeBtn.addEventListener('mouseleave', function() {
        this.style.backgroundColor = 'rgba(255,255,255,0.9)';
        this.style.color = '#666';
    });
    
    // Add content first, then close button to ensure it's on top
    modalContent.innerHTML = content;
    modalContent.appendChild(closeBtn);
    modal.appendChild(modalContent);
    
    // Close on backdrop click
    modal.addEventListener('click', function(e) {
        if (e.target === modal) {
            closeModal();
        }
    });
    
    // Close on escape key
    function handleEscape(e) {
        if (e.key === 'Escape' || e.keyCode === 27) {
            closeModal();
            document.removeEventListener('keydown', handleEscape);
        }
    }
    document.addEventListener('keydown', handleEscape);
    
    document.body.appendChild(modal);
    
    // Prevent body scroll when modal is open
    document.body.style.overflow = 'hidden';
    
    // Cleanup function
    const originalRemove = modal.remove;
    modal.remove = function() {
        document.body.style.overflow = '';
        document.removeEventListener('keydown', handleEscape);
        originalRemove.call(this);
    };
}

function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}
</script>

<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>

<?php 
$hideAdverts = true;
include 'includes/footer.php'; 
?>

