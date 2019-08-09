var ctx = document.getElementById('ApiChart').getContext('2d');
var ApiChart = new Chart(ctx, {
    type: 'line',
    data: {
        labels: ApichartData.labels,
        datasets: [
            {
                label: 'All',
                data: ApichartData.data.all,
                borderColor : '#CCCCCC',
                backgroundColor : '#00000000',
                borderWidth: 1,
                hidden: true,
            },
            {
                label: 'GET',
                data: ApichartData.data.GET ,
                borderColor : '#44EE44',
                backgroundColor : '#44EE4433',
                borderWidth: 1,
            },
            {
                label: 'POST',
                data: ApichartData.data.POST ,
                borderColor : '#883997',
                backgroundColor : '#88399733',
                borderWidth: 1,
            },
            {
                label: 'PUT',
                data: ApichartData.data.PUT ,
                borderColor : '#ffb300',
                backgroundColor : '#ffb30033',
                borderWidth: 1,
            },
            {
                label: 'PATCH',
                data: ApichartData.data.PATCH ,
                borderColor: '#f5fd67',
                backgroundColor : '#f5fd6733',
                borderWidth: 1,
            },
            {
                label: 'DELETE',
                data: ApichartData.data.DELETE ,
                borderColor : '#ab000d',
                backgroundColor : '#ab000d44',
                borderWidth: 1,
            }
        ]
    },
    options: {
        scales: {
            yAxes: [{
                ticks: {
                    beginAtZero: true
                }
            }]
        }
    }
});