import Chart from 'chart.js/auto';

document.addEventListener('DOMContentLoaded', () => {
    const usersChart = document.getElementById('usersByRoleChart');

    if (usersChart) {
        new Chart(usersChart, {
            type: 'doughnut',
            data: {
                labels: window.dashboardData.usersByRole.labels,
                datasets: [{
                    data: window.dashboardData.usersByRole.values,
                }],
            },
            options: {
                responsive: true,
                plugins: {
                    legend: {
                        position: 'bottom',
                    },
                },
            },
        });
    }

    const tokensChart = document.getElementById('tokensChart');

    if (tokensChart) {
        new Chart(tokensChart, {
            type: 'bar',
            data: {
                labels: window.dashboardData.tokens.labels,
                datasets: [{
                    label: 'Tokens',
                    data: window.dashboardData.tokens.values,
                }],
            },
            options: {
                responsive: true,
            },
        });
    }
});