(() => {
    const selector = document.querySelector('#dashboard-range');
    const charts = document.querySelector('#dashboard-charts');
    const dataElement = document.querySelector('#dashboard-series');

    if (!selector || !charts || !dataElement) return;

    const data = JSON.parse(dataElement.textContent);
    const namespace = 'http://www.w3.org/2000/svg';
    const chartLabels = {
        copies: 'Copies added',
        checkouts: 'Checkouts started',
        active: 'Active checkouts'
    };
    const chartUnits = {
        copies: 'copies',
        checkouts: 'checkouts',
        active: 'checkouts'
    };

    function addSvgElement(svg, name, attributes, text = '') {
        const element = document.createElementNS(namespace, name);
        Object.entries(attributes).forEach(([key, value]) => {
            element.setAttribute(key, value);
        });
        if (text) element.textContent = text;
        svg.append(element);
        return element;
    }

    function renderChart(svg, points, metric, rangeLabel) {
        svg.replaceChildren();
        svg.setAttribute(
            'aria-label',
            `${chartLabels[metric]} for ${rangeLabel.toLowerCase()}`
        );

        const width = 360;
        const height = 180;
        const left = 38;
        const right = 10;
        const top = 14;
        const bottom = 32;
        const plotWidth = width - left - right;
        const plotHeight = height - top - bottom;
        const values = points.map((point) => Number(point[metric]) || 0);
        const maxValue = Math.max(1, ...values);
        const x = (index) => points.length < 2
            ? left + plotWidth / 2
            : left + (index / (points.length - 1)) * plotWidth;
        const y = (value) => top + plotHeight - (value / maxValue) * plotHeight;

        [0, 0.5, 1].forEach((fraction) => {
            const value = Math.round(maxValue * fraction);
            const gridY = y(value);
            addSvgElement(svg, 'line', {
                x1: left,
                y1: gridY,
                x2: width - right,
                y2: gridY,
                class: 'dashboard-chart-gridline'
            });
            addSvgElement(svg, 'text', {
                x: left - 6,
                y: gridY + 4,
                'text-anchor': 'end',
                class: 'dashboard-chart-axis'
            }, String(value));
        });

        const coordinates = points.map((point, index) => ({
            x: x(index),
            y: y(Number(point[metric]) || 0),
            point
        }));

        if (coordinates.length > 1) {
            addSvgElement(svg, 'polyline', {
                points: coordinates.map(({ x: pointX, y: pointY }) =>
                    `${pointX},${pointY}`
                ).join(' '),
                class: 'dashboard-line'
            });
        }

        coordinates.forEach(({ x: pointX, y: pointY, point }) => {
            const value = Number(point[metric]) || 0;
            const marker = addSvgElement(svg, 'circle', {
                cx: pointX,
                cy: pointY,
                r: 3,
                class: 'dashboard-chart-point'
            });
            addSvgElement(
                marker,
                'title',
                {},
                `${point.label}: ${value} ${chartUnits[metric]}`
            );
        });

        const labelStep = Math.max(1, Math.ceil(points.length / 6));
        coordinates.forEach(({ x: pointX, point }, index) => {
            if (index % labelStep !== 0 && index !== points.length - 1) return;
            addSvgElement(svg, 'text', {
                x: pointX,
                y: height - 8,
                'text-anchor': 'middle',
                class: 'dashboard-chart-month'
            }, point.label);
        });
    }

    function render() {
        const selected = data[selector.value];
        if (!selected || !Array.isArray(selected.points)) return;

        charts.querySelectorAll('[data-series]').forEach((panel) => {
            const metric = panel.dataset.series;
            const svg = panel.querySelector('svg');
            if (svg && chartLabels[metric]) {
                renderChart(svg, selected.points, metric, selected.label);
            }
        });
    }

    selector.addEventListener('change', render);
    render();
})();
