<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Crypto Trading Bot Reports</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <style>
        body { padding-top: 20px; }
        .container { background-color: #f8f9fa; padding: 30px; border-radius: 8px; box-shadow: 0 0 10px rgba(0,0,0,0.1); }
        h1 { color: #007bff; margin-bottom: 30px; }
        #reportsList { margin-top: 20px; }
        #reportsList li { margin-bottom: 10px; }
    </style>
</head>
<body>
    <div class="container">
        <h1 class="text-center">Generated Reports</h1>
        <div class="row">
            <div class="col-md-12">
                <ul id="reportsList" class="list-group"></ul>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            fetch('api/get-reports.php')
                .then(response => response.json())
                .then(data => {
                    const reportsList = document.getElementById('reportsList');
                    if (data.status === 'success' && data.reports.length > 0) {
                        data.reports.forEach(report => {
                            const listItem = document.createElement('li');
                            listItem.className = 'list-group-item';
                            const link = document.createElement('a');
                            link.href = `reports/${report}`;
                            link.textContent = report;
                            link.target = '_blank'; // Open in new tab
                            listItem.appendChild(link);
                            reportsList.appendChild(listItem);
                        });
                    } else {
                        const listItem = document.createElement('li');
                        listItem.className = 'list-group-item text-muted';
                        listItem.textContent = 'No reports found.';
                        reportsList.appendChild(listItem);
                    }
                })
                .catch(error => {
                    console.error('Error fetching reports:', error);
                    const reportsList = document.getElementById('reportsList');
                    const listItem = document.createElement('li');
                    listItem.className = 'list-group-item text-danger';
                    listItem.textContent = 'Failed to load reports.';
                    reportsList.appendChild(listItem);
                });
        });
    </script>
</body>
</html>