<!DOCTYPE html><?php session_start(); ?>
<html style="font-size: 16px;" lang="fr">

<head>
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<meta charset="utf-8">
	<title>Question</title>
	<link rel="stylesheet" href="nicepage.css" media="screen">
	<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
	<link rel="stylesheet" href="Question.css" media="screen">

    <style>
        .chart-container {
            display: flex;
            flex-wrap: wrap;
            justify-content: center;
            gap: 20px;
            margin-top: 20px;
        }

        .chart-box {
            width: 18em;
            height: 30em;
			margin-bottom: 3em;
        }
    </style>
<body data-path-to-root="./" data-include-products="false" class="u-body u-xl-mode" data-lang="fr" style="height:100%">
	<section  id="sec-089e">
		<div class="u-container-style u-expanded-width u-grey-10 u-group u-group-1">
			<div class="u-container-layout u-container-layout-1">
				<div class="u-clearfix u-sheet u-sheet-1" style="text-align: center;">
				<div id="chartsContainer" class="chart-container"></div>
				</div>
			</div>
		</div>
	</section>

    <script>
        const chartInstances = {};
        fetch('stats_getdata.php')
            .then(response => response.json())
            .then(data => {
                data.formattedData.forEach((item, index) => {
                    if (item.type === 'qcm' || item.type === 'echelle') {
                        createPieChart(item.question, item.responses, item.id);
                    } else if (item.type === 'mct') {
                        createStackedBarChart(item.sub_questions, item.responses, item.id, item.question);
                    } else if (item.type === 'lien') {
                        createStackedBarChart(item.sub_questions, item.sub_responses, item.id, item.question);
                    }
                });

                data.answers.forEach((item, index) => {

                    let questionId = item.question;
                    let responseId = item.response;
                    let chart = chartInstances[parseInt(questionId)];
                    if (!responseId) {
                        if (chart) {
                            let newSubResponses = item.subresponse.split(",");
                            let newSubQuestion = item.subquestion.split(",");


                            let dataRep = newSubResponses.map(Number);
                            let dataQuest = newSubQuestion.map(Number);
                            dataQuest.forEach((questF, index) => {
                                chart.data.datasets[dataRep[index] - 1].data[questF - 1]++;
                            });

                            chart.update();
                        }
                    }
                    else
                        if (chart) {
                            chart.data.datasets[0].data[responseId] += 1;
                            chart.update();
                        }


                });
            })
            .catch(error => console.error('Error:', error));

        function createPieChart(question, responses, chartIndex) {
            const validResponses = responses.filter(response => response !== "null");
            let container = document.getElementById("chartsContainer");
            let div = document.createElement("div");
            div.className = "chart-box";
            let questionLabel = document.createElement("div");
            questionLabel.textContent = `Question: ${question}`;
            questionLabel.style.textAlign = "center";
            div.appendChild(questionLabel);
            let canvas = document.createElement("canvas");
            canvas.id = "chart_" + chartIndex;
            div.appendChild(canvas);
            container.appendChild(div);
            const chart = new Chart(canvas, {
                type: 'pie',
                data: {
                    labels: [question, ...validResponses],
                    datasets: [{
                        data: [0, ...validResponses.map(() => 0)],
                        backgroundColor: ["Blue", "#FF0080", "Yellow", "Orange", "Red"]
                    }]
                },
                options: {
                    plugins: {
                        tooltip: {
                            enabled: true
                        }
                    },
                    interaction: {
                        mode: 'nearest'
                    },
                    responsive: false,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            display: false
                        }
                    }
                }
            });
            chartInstances[chartIndex] = chart;
        }
        function createStackedBarChart(subQuestions, responses, chartIndex, question) {
            const validResponses = responses.filter(response => response !== "null");
            let container = document.getElementById("chartsContainer");
            let canvas = document.createElement("canvas");
            canvas.id = "chart_" + chartIndex;
            let div = document.createElement("div");
            let questionLabel = document.createElement("div");
            questionLabel.textContent = `Question: ${question}`;
            questionLabel.style.textAlign = "center";
            div.appendChild(questionLabel);
            div.className = "chart-box";
            div.appendChild(canvas);
            
            container.appendChild(div);
            let datasets = responses.map((response, index) => ({
                label: response.slice(0, 32) + "...",
                data: response.slice(0, 32) === "null" ? subQuestions.map(() => 0) : subQuestions.map(() => 0),
                backgroundColor: `hsl(${index * 137.508}, 70%, 50%)`
            }));

            const chart = new Chart(canvas, {
                type: 'bar',
                data: {
                    labels: subQuestions,
                    datasets: datasets
                },
                options: {

                    scales: {
                        x: { beginAtZero: true, stacked: true },
                        y: {beginAtZero: true, stacked: true}
                    },
                    interaction: {
                        mode: 'nearest'
                    },
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            display: false
                        }
                    }
                }
            });
            chartInstances[chartIndex] = chart;

        }
    </script>
	<script>
		setTimeout(() => {
			const section = document.querySelector('section');
			const newDiv = document.createElement('div');
			newDiv.id = "footer-placeholder";
			section.insertAdjacentElement('afterend', newDiv);
			fetch('pages/footer.php')
				.then(response => response.text())
				.then(data => {
					document.getElementById('footer-placeholder').innerHTML = data;
				});
		}, 100); 
	</script>


</body>

</html>
