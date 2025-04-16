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
            width: 22em;
            height: 35em;
            margin-bottom: 3em;
        }

        .legend-container {
            margin-top: 10px;
            text-align: center;
            display: flex;
            flex-direction: column;
            align-items: center;
        }

        .legend-item {
            display: flex;
            align-items: center;
            margin: 2px 0;
            font-size: 14px;
            width: 100%;
            justify-content: center;
            text-align: center;
        }

        .legend-label {
            flex: 1;
            word-wrap: break-word;
            white-space: normal;
            max-width: 18em;
        }

        .legend-color {
            width: 15px;
            height: 15px;
            margin-right: 5px;
            display: inline-block;
            flex-shrink: 0;
        }

        .count {
            margin-left: 5px;
            flex-shrink: 0;
        }
    </style>
<body data-path-to-root="./" data-include-products="false" class="u-body u-xl-mode" data-lang="fr" style="height:100%">
	<section id="sec-089e">
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
                        if (chart && item.subresponse && item.subquestion) {
                            let newSubResponses = item.subresponse.split(",");
                            let newSubQuestion = item.subquestion.split(",");

                            let dataRep = newSubResponses.map(Number);
                            let dataQuest = newSubQuestion.map(Number);
                            dataQuest.forEach((questF, index) => {
                                chart.data.datasets[dataRep[index] - 1].data[questF - 1]++;
                            });

                            chart.update();

                            // Update legend counts for StackedBarChart
                            let legendItems = document.querySelectorAll(`#chart_${parseInt(questionId)} + .legend-container .legend-item`);
                            legendItems.forEach((item, idx) => {
                                let total = chart.data.datasets[idx].data.reduce((sum, val) => sum + val, 0);
                                let countSpan = item.querySelector(".count");
                                countSpan.textContent = `(${total})`;
                            });
                        }
                    }
                    else if (chart) {
                        chart.data.datasets[0].data[responseId] += 1;
                        chart.update();

                        // Update the legend with the new counts for PieChart
                        let legendItems = document.querySelectorAll(`#chart_${parseInt(questionId)} + .legend-container .legend-item`);
                        legendItems.forEach((item, idx) => {
                            let countSpan = item.querySelector(".count");
                            countSpan.textContent = `(${chart.data.datasets[0].data[idx + 1]})`;
                        });
                    }
                });
            })
            .catch(error => console.error('Error:', error));

        function createPieChart(question, responses, chartIndex) {
            const validResponses = responses.filter(response => response !== "null");
            let container = document.getElementById("chartsContainer");
            let div = document.createElement("div");
            div.className = "chart-box";
            
            // Add question label
            let questionLabel = document.createElement("div");
            questionLabel.textContent = `Question: ${question}`;
            questionLabel.style.textAlign = "center";
            div.appendChild(questionLabel);

            // Add canvas for chart
            let canvas = document.createElement("canvas");
            canvas.id = "chart_" + chartIndex;
            div.appendChild(canvas);

            // Create chart
            const backgroundColors = ["Blue", "#FF0080", "Yellow", "Orange", "Red"];
            const chart = new Chart(canvas, {
                type: 'pie',
                data: {
                    labels: [question, ...validResponses],
                    datasets: [{
                        data: [0, ...validResponses.map(() => 0)],
                        backgroundColor: backgroundColors
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

            // Create legend
            let legendContainer = document.createElement("div");
            legendContainer.className = "legend-container";
            validResponses.forEach((response, index) => {
                let legendItem = document.createElement("div");
                legendItem.className = "legend-item";
                
                let colorBox = document.createElement("span");
                colorBox.className = "legend-color";
                colorBox.style.backgroundColor = backgroundColors[index + 1];
                legendItem.appendChild(colorBox);

                let label = document.createElement("span");
                label.className = "legend-label";
                label.textContent = response.length > 20 ? response.slice(0, 20) + "..." : response;
                legendItem.appendChild(label);

                let countSpan = document.createElement("span");
                countSpan.className = "count";
                countSpan.textContent = `(${chart.data.datasets[0].data[index + 1]})`;
                legendItem.appendChild(countSpan);

                legendContainer.appendChild(legendItem);
            });

            div.appendChild(legendContainer);
            container.appendChild(div);
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
                data: subQuestions.map(() => 0),
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
                        y: { beginAtZero: true, stacked: true }
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

            // Create legend for StackedBarChart
            let legendContainer = document.createElement("div");
            legendContainer.className = "legend-container";
            responses.forEach((response, index) => {
                let legendItem = document.createElement("div");
                legendItem.className = "legend-item";
                
                let colorBox = document.createElement("span");
                colorBox.className = "legend-color";
                colorBox.style.backgroundColor = datasets[index].backgroundColor;
                legendItem.appendChild(colorBox);

                let label = document.createElement("span");
                label.className = "legend-label";
                label.textContent = response.length > 20 ? response.slice(0, 20) + "..." : response;
                legendItem.appendChild(label);

                let countSpan = document.createElement("span");
                countSpan.className = "count";
                countSpan.textContent = `(0)`; // Initial count
                legendItem.appendChild(countSpan);

                legendContainer.appendChild(legendItem);
            });

            div.appendChild(legendContainer);
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
