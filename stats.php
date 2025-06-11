<!DOCTYPE html>
<?php session_start(); ?>
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
            flex-direction: column; /* Располагаем графики вертикально */
            align-items: center; /* Центрируем их по горизонтали */
            gap: 30px; /* Увеличиваем отступ между графиками */
            margin-top: 20px;
        }
        .chart-box {
            width: 90%; /* Делаем блок с графиком шире */
            max-width: 80em; /* Ограничиваем максимальную ширину для больших экранов */
            height: auto;
            min-height: 20em;
            margin-bottom: 2em;
            display: flex;
            flex-direction: column;
            align-items: center;
            border: 1px solid #ddd; /* Добавим рамку для наглядности */
            border-radius: 8px;
            padding: 15px;
        }
        .chart-box canvas {
            max-height: 20em !important; /* Можно немного увеличить высоту самого графика */
            width: 100% !important;
        }
        .legend-container {
            margin-top: 15px; /* Увеличим отступ сверху */
            text-align: left; /* Выравниваем текст в контейнере по левому краю */
            display: flex;
            flex-direction: column;
            align-items: flex-start; /* Выравниваем элементы по левому краю */
            width: 100%;
        }
        .legend-item {
            display: flex;
            align-items: center;
            margin: 3px 0;
            font-size: 14px; /* Немного увеличим шрифт для читаемости */
            width: 100%;
            justify-content: flex-start; /* Выравниваем элементы по левому краю */
        }
        .legend-label {
            flex-shrink: 1;
            word-wrap: break-word;
            white-space: normal;
            max-width: none; /* Убираем ограничение на ширину текста */
            text-align: left;
        }
        .legend-color {
            width: 14px;
            height: 14px;
            margin-right: 8px; /* Увеличим отступ */
            display: inline-block;
            flex-shrink: 0;
        }
        .count {
            margin-left: 8px;
            flex-shrink: 0;
            font-weight: bold;
        }
        .language-buttons {
            text-align: center;
            margin: 10px 0;
        }
        .language-buttons button {
            margin: 0 5px;
            padding: 8px 16px;
            cursor: pointer;
            background-color: #007bff;
            color: white;
            border: none;
            border-radius: 4px;
        }
        .language-buttons button:hover {
            background-color: #0056b3;
        }
        .language-buttons button.active {
            background-color: #28a745;
        }
        .count-box {
            background-color: #f8f9fa;
            border: 2px solid #007bff;
            border-radius: 10px;
            padding: 10px 20px;
            margin: 10px auto;
            width: fit-content;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
            transition: background-color 0.3s ease;
        }

        .count-box:hover {
            background-color: #e9ecef;
        }

        #totalCountText {
            font-size: 18px;
            font-weight: bold;
            color: #333;
        }
    </style>
</head>
<body data-path-to-root="./" data-include-products="false" class="u-body u-xl-mode" data-lang="fr" style="height:100%">
    <section id="sec-089e">
        <div class="u-container-style u-expanded-width u-grey-10 u-group u-group-1">
            <div class="u-container-layout u-container-layout-1">
                <div class="u-clearfix u-sheet u-sheet-1" style="text-align: center;">
                    <div class="language-buttons">
                        <button onclick="loadStats('fr')" class="lang-btn" data-lang="fr">Français</button>
                        <button onclick="loadStats('de')" class="lang-btn" data-lang="de">Deutsch</button>
                        <button onclick="loadStats('all')" class="lang-btn active" data-lang="all">All</button>
                    </div>
                    <div id="totalCount" class="count-box">
                        <span id="totalCountText">Total responses: 0 (All)</span>
                    </div>
                    <div id="chartsContainer" class="chart-container"></div>
                </div>
            </div>
        </div>
    </section>

    <script>
        const chartInstances = {};
function loadStats(lang) {            
    document.querySelectorAll('.lang-btn').forEach(btn => {
        btn.classList.remove('active');
        if (btn.dataset.lang === lang) {
            btn.classList.add('active');
        }
    });

    const container = document.getElementById('chartsContainer');
    container.innerHTML = '';

    Object.keys(chartInstances).forEach(key => {
        chartInstances[key].destroy();
        delete chartInstances[key];
    });

    fetch(`stats_getdata.php?lang=${lang}`)
        .then(response => response.json())
        .then(data => {
            const totalText = {
                'fr': 'Réponses totales',
                'de': 'Gesamtanzahl der Antworten',
                'all': 'Total responses'
            };
            const languageLabel = {
                'fr': 'Français',
                'de': 'Deutsch',
                'all': 'All'
            };

            const totalResponses = data.totalResponses || 0;
            document.getElementById('totalCountText').textContent = `${totalText[lang]}: ${totalResponses} (${languageLabel[lang]})`;

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
                        let legendItems = document.querySelectorAll(`#chart_${parseInt(questionId)} + .legend-container .legend-item`);
                        legendItems.forEach((item, idx) => {
                            let total = chart.data.datasets[idx].data.reduce((sum, val) => sum + val, 0);
                            let countSpan = item.querySelector(".count");
                            countSpan.textContent = `(${total})`;
                        });
                    }
                } else if (chart) {
                    chart.data.datasets[0].data[responseId] += 1;
                    chart.update();
                    let legendItems = document.querySelectorAll(`#chart_${parseInt(questionId)} + .legend-container .legend-item`);
                    legendItems.forEach((item, idx) => {
                        let countSpan = item.querySelector(".count");
                        countSpan.textContent = `(${chart.data.datasets[0].data[idx + 1]})`;
                    });
                }
            });
        })
        .catch(error => console.error('Error:', error));
}

        function createPieChart(question, responses, chartIndex) {
            const validResponses = responses.filter(response => response !== "null");
            let container = document.getElementById("chartsContainer");
            let div = document.createElement("div");
            div.className = "chart-box";
            let questionLabel = document.createElement("div");
            questionLabel.innerHTML = `<b>Question: ${question}</b>`;
            questionLabel.style.textAlign = "center";
            questionLabel.style.marginBottom = "10px";
            div.appendChild(questionLabel);
            let canvas = document.createElement("canvas");
            canvas.id = "chart_" + chartIndex;
            div.appendChild(canvas);
            const backgroundColors = ["Blue", "#FF0080", "Yellow", "Orange", "Red"];
            const chart = new Chart(canvas, {
                type: 'pie',
                data: {
                    labels: validResponses, // Убрал question из labels для Pie
                    datasets: [{
                        data: validResponses.map(() => 0), // Инициализируем нули для каждого ответа
                        backgroundColor: backgroundColors
                    }]
                },
                options: {
                    plugins: {
                        tooltip: { enabled: true }
                    },
                    interaction: { mode: 'nearest' },
                    responsive: true, // Включаем адаптивность
                    maintainAspectRatio: false,
                    plugins: { legend: { display: false } }
                }
            });
            chartInstances[chartIndex] = chart;
            let legendContainer = document.createElement("div");
            legendContainer.className = "legend-container";
            validResponses.forEach((response, index) => {
                let legendItem = document.createElement("div");
                legendItem.className = "legend-item";
                let colorBox = document.createElement("span");
                colorBox.className = "legend-color";
                colorBox.style.backgroundColor = backgroundColors[index]; // Корректный индекс цвета
                legendItem.appendChild(colorBox);
                let label = document.createElement("span");
                label.className = "legend-label";
                label.textContent = response; // ИЗМЕНЕНИЕ: Убрано усечение текста
                legendItem.appendChild(label);
                let countSpan = document.createElement("span");
                countSpan.className = "count";
                // Начинаем с (0), будем обновлять позже
                countSpan.textContent = `(0)`;
                legendItem.appendChild(countSpan);
                legendContainer.appendChild(legendItem);
            });
            div.appendChild(legendContainer);
            container.appendChild(div);
        }

        function createStackedBarChart(subQuestions, responses, chartIndex, question) {
            const validResponses = responses.filter(response => response !== "null");
            let container = document.getElementById("chartsContainer");
            let div = document.createElement("div");
            div.className = "chart-box";
            let questionLabel = document.createElement("div");
            questionLabel.innerHTML = `<b>Question: ${question}</b>`;
            questionLabel.style.textAlign = "center";
            questionLabel.style.marginBottom = "10px";
            div.appendChild(questionLabel);
            let canvas = document.createElement("canvas");
            canvas.id = "chart_" + chartIndex;
            div.appendChild(canvas);
            container.appendChild(div);
            let datasets = responses.map((response, index) => ({
                label: response, // ИЗМЕНЕНИЕ: Убрано усечение текста
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
                    interaction: { mode: 'nearest' },
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: { legend: { display: false } }
                }
            });
            chartInstances[chartIndex] = chart;
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
                label.textContent = response; // ИЗМЕНЕНИЕ: Убрано усечение текста
                legendItem.appendChild(label);
                let countSpan = document.createElement("span");
                countSpan.className = "count";
                countSpan.textContent = `(0)`;
                legendItem.appendChild(countSpan);
                legendContainer.appendChild(legendItem);
            });
            div.appendChild(legendContainer);
        }

        loadStats('all');
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
