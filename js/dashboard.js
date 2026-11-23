(() => {
    const selector = document.querySelector('#dashboard-range');
    const charts = document.querySelector('#dashboard-charts');
    const dataElement = document.querySelector('#dashboard-series');
    const overviewElement = document.querySelector('#dashboard-overview-series');
    const reportElement = document.querySelector('#dashboard-report-data');
    const reportDialog = document.querySelector('#dashboard-report-dialog');
    const reportForm = document.querySelector('#dashboard-report-form');
    const reportTypeSelector = document.querySelector('#dashboard-report-type');
    const reportFormatSelector = document.querySelector('#dashboard-report-format');
    const reportButton = document.querySelector('#open-dashboard-report');
    const reportPeriod = document.querySelector('#dashboard-report-period');
    const reportStatus = document.querySelector('#dashboard-report-status');

    if (!selector) return;

    const data = dataElement ? JSON.parse(dataElement.textContent) : {};
    const overviewData = overviewElement
        ? JSON.parse(overviewElement.textContent)
        : {};
    const reportData = reportElement ? JSON.parse(reportElement.textContent) : {};
    const namespace = 'http://www.w3.org/2000/svg';
    const numberFormatter = new Intl.NumberFormat();
    const currencyFormatter = new Intl.NumberFormat(undefined, {
        style: 'currency',
        currency: 'USD'
    });
    const chartLabels = {
        copies: 'Copies added',
        transactions: 'Transactions',
        inventory: 'Inventory total',
        active: 'Active checkouts'
    };
    const chartUnits = {
        copies: 'copies',
        transactions: 'transactions',
        inventory: 'copies',
        active: 'checkouts'
    };
    function createCell(row, tag, value, scope = null) {
        const cell = document.createElement(tag);
        cell.textContent = value;
        if (scope) cell.scope = scope;
        row.append(cell);
    }

    function matchesSelectedPeriod(date) {
        const selectedRange = selector.value;
        const [start, end] = reportData.ranges?.[selectedRange] ?? [];
        if (selectedRange === 'all' && !date) return true;
        return Boolean(date && start && end && date >= start && date < end);
    }

    function renderRecentCheckouts() {
        const body = document.querySelector('#recent-checkouts-body');
        const wrapper = document.querySelector('#recent-checkouts-wrap');
        const emptyMessage = document.querySelector('#recent-checkouts-empty');
        if (!body || !wrapper || !emptyMessage) return;

        const checkouts = (reportData.checkouts ?? [])
            .filter((checkout) => matchesSelectedPeriod(checkout.checkout_date))
            .slice(0, 6);
        body.replaceChildren();
        checkouts.forEach((checkout) => {
            const row = document.createElement('tr');
            createCell(row, 'td', checkout.patron_name);
            createCell(row, 'td', checkout.item_title);
            createCell(row, 'td', checkout.due_date || 'Not set');
            const statusCell = document.createElement('td');
            const status = document.createElement('span');
            const isReturned = Boolean(checkout.return_date);
            status.className = `dashboard-loan-status${isReturned ? ' is-returned' : ''}`;
            status.textContent = isReturned ? 'Returned' : 'On loan';
            statusCell.append(status);
            row.append(statusCell);
            body.append(row);
        });

        wrapper.hidden = checkouts.length === 0;
        emptyMessage.hidden = checkouts.length !== 0;
    }

    function buildReport() {
        const reportType = reportTypeSelector.value;
        const today = reportData.today;
        const upcomingEnd = reportData.upcomingEnd;
        const overdue = reportType === 'overdue';
        const selectedPeriod = selector.selectedOptions[0]?.textContent
            ?? 'selected period';
        const currency = (amount) => currencyFormatter.format(Number(amount) || 0);
        const dateLabel = (date) => date || 'Undated';
        const typeLabel = reportTypeSelector.selectedOptions[0]?.textContent
            ?? 'Library';
        const checkouts = (reportData.checkouts ?? []).filter((checkout) => {
            if (!checkout.due_date) return false;
            if (overdue) {
                const overdueThrough = checkout.return_date || today;
                return checkout.due_date < overdueThrough &&
                    matchesSelectedPeriod(checkout.checkout_date);
            }
            return !checkout.return_date && checkout.due_date >= today &&
                checkout.due_date <= upcomingEnd;
        }).sort((left, right) =>
            left.due_date.localeCompare(right.due_date) ||
            left.item_title.localeCompare(right.item_title)
        );
        const periodLabel = overdue
            ? `Overdue loans checked out ${selectedPeriod.toLowerCase()}`
            : `Due within 3 days (${today} through ${upcomingEnd})`;
        const section = {
            title: overdue ? 'Overdue items' : 'Upcoming due items',
            headings: overdue
                ? ['Item', 'Media type', 'Patron', 'Due date', 'Loan status', 'Outstanding fines']
                : ['Item', 'Media type', 'Patron', 'Due date'],
            rows: checkouts.map((checkout) => [
                checkout.item_title,
                checkout.item_type || 'Media',
                checkout.patron_name,
                dateLabel(checkout.due_date),
                ...(overdue ? [
                    checkout.return_date
                        ? `Returned late (${dateLabel(checkout.return_date)})`
                        : 'Currently overdue',
                    currency(checkout.outstanding_fine)
                ] : [])
            ])
        };

        return {
            title: `${typeLabel} — ${periodLabel}`,
            sections: [section]
        };
    }

    function escapeXml(value) {
        return String(value)
            .replace(/[\u0000-\u0008\u000B\u000C\u000E-\u001F]/g, '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&apos;');
    }

    function reportStatusKind(value) {
        if (value === 'Currently overdue') return 'current';
        if (value.startsWith('Returned late (')) return 'returned';
        return '';
    }

    function makeDocx(report) {
        const paragraph = (text, style = '') =>
            `<w:p>${style ? `<w:pPr><w:pStyle w:val="${style}"/></w:pPr>` : ''}` +
            `<w:r><w:t xml:space="preserve">${escapeXml(text)}</w:t></w:r></w:p>`;
        const tableRow = (cells, header = false) =>
            `<w:tr>${cells.map((cell) =>
                `<w:tc><w:tcPr><w:tcW w:w="0" w:type="auto"/>` +
                `${!header && reportStatusKind(cell) === 'current'
                    ? '<w:shd w:fill="FCE8E6"/>'
                    : !header && reportStatusKind(cell) === 'returned'
                        ? '<w:shd w:fill="E8F3EB"/>'
                        : ''}</w:tcPr>` +
                `<w:p><w:r>${header ? '<w:rPr><w:b/></w:rPr>' :
                    reportStatusKind(cell) === 'current'
                        ? '<w:rPr><w:b/><w:color w:val="B42318"/></w:rPr>'
                        : reportStatusKind(cell) === 'returned'
                            ? '<w:rPr><w:b/><w:color w:val="237A45"/></w:rPr>'
                            : ''}` +
                `<w:t xml:space="preserve">${escapeXml(cell)}</w:t></w:r></w:p></w:tc>`
            ).join('')}</w:tr>`;
        const body = [
            paragraph(report.title, 'Title'),
            paragraph(`Generated ${new Date().toLocaleString()}`)
        ];

        report.sections.forEach((section) => {
            body.push(paragraph(section.title, 'Heading1'));
            if (section.rows.length === 0) {
                body.push(paragraph(`No ${section.title.toLowerCase()} for this period.`));
                return;
            }
            body.push(
                `<w:tbl><w:tblPr><w:tblBorders>` +
                ['top', 'left', 'bottom', 'right', 'insideH', 'insideV'].map((edge) =>
                    `<w:${edge} w:val="single" w:sz="4" w:space="0" w:color="B7C9BC"/>`
                ).join('') +
                `</w:tblBorders></w:tblPr>${tableRow(section.headings, true)}` +
                section.rows.map((row) => tableRow(row)).join('') +
                '</w:tbl>'
            );
        });
        body.push('<w:sectPr><w:pgSz w:w="12240" w:h="15840"/>' +
            '<w:pgMar w:top="720" w:right="720" w:bottom="720" w:left="720"/>' +
            '</w:sectPr>');

        return makeZip([
            {
                name: '[Content_Types].xml',
                content: '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' +
                    '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">' +
                    '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>' +
                    '<Default Extension="xml" ContentType="application/xml"/>' +
                    '<Override PartName="/word/document.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/>' +
                    '</Types>'
            },
            {
                name: '_rels/.rels',
                content: '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' +
                    '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">' +
                    '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="word/document.xml"/>' +
                    '</Relationships>'
            },
            {
                name: 'word/document.xml',
                content: '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' +
                    '<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">' +
                    `<w:body>${body.join('')}</w:body></w:document>`
            }
        ]);
    }

    function makeZip(files) {
        const encoder = new TextEncoder();
        const crc32 = (bytes) => {
            let crc = 0xffffffff;
            bytes.forEach((byte) => {
                crc ^= byte;
                for (let bit = 0; bit < 8; bit++) {
                    crc = (crc >>> 1) ^ ((crc & 1) ? 0xedb88320 : 0);
                }
            });
            return (crc ^ 0xffffffff) >>> 0;
        };
        const header = (signature, length) => {
            const bytes = new Uint8Array(length);
            new DataView(bytes.buffer).setUint32(0, signature, true);
            return bytes;
        };
        const localParts = [];
        const centralParts = [];
        let localOffset = 0;

        files.forEach(({ name, content }) => {
            const nameBytes = encoder.encode(name);
            const contentBytes = encoder.encode(content);
            const crc = crc32(contentBytes);
            const local = header(0x04034b50, 30 + nameBytes.length);
            const localView = new DataView(local.buffer);
            localView.setUint16(4, 20, true);
            localView.setUint32(14, crc, true);
            localView.setUint32(18, contentBytes.length, true);
            localView.setUint32(22, contentBytes.length, true);
            localView.setUint16(26, nameBytes.length, true);
            local.set(nameBytes, 30);
            localParts.push(local, contentBytes);

            const central = header(0x02014b50, 46 + nameBytes.length);
            const centralView = new DataView(central.buffer);
            centralView.setUint16(4, 20, true);
            centralView.setUint16(6, 20, true);
            centralView.setUint32(16, crc, true);
            centralView.setUint32(20, contentBytes.length, true);
            centralView.setUint32(24, contentBytes.length, true);
            centralView.setUint16(28, nameBytes.length, true);
            centralView.setUint32(42, localOffset, true);
            central.set(nameBytes, 46);
            centralParts.push(central);
            localOffset += local.length + contentBytes.length;
        });

        const centralSize = centralParts.reduce((sum, part) => sum + part.length, 0);
        const end = header(0x06054b50, 22);
        const endView = new DataView(end.buffer);
        endView.setUint16(8, files.length, true);
        endView.setUint16(10, files.length, true);
        endView.setUint32(12, centralSize, true);
        endView.setUint32(16, localOffset, true);
        return new Blob([...localParts, ...centralParts, end], {
            type: 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'
        });
    }

    function downloadReport(blob, filename) {
        const url = URL.createObjectURL(blob);
        const link = document.createElement('a');
        link.href = url;
        link.download = filename;
        document.body.append(link);
        link.click();
        link.remove();
        window.setTimeout(() => URL.revokeObjectURL(url), 1000);
    }

    function reportText(report) {
        const lines = [report.title, `Generated ${new Date().toLocaleString()}`, ''];
        report.sections.forEach((section) => {
            lines.push(section.title, section.headings.join('\t'));
            if (section.rows.length === 0) {
                lines.push(`No ${section.title.toLowerCase()} for this period.`);
            } else {
                section.rows.forEach((row) => lines.push(row.join('\t')));
            }
            lines.push('');
        });
        return lines.join('\n');
    }

    function escapeHtml(value) {
        return String(value).replace(/[&<>"']/g, (character) => ({
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            '"': '&quot;',
            "'": '&#39;'
        })[character]);
    }

    function openPdfPrintDialog(report) {
        const printWindow = window.open('', '_blank');
        if (!printWindow) return false;
        const sections = report.sections.map((section) => {
            const content = section.rows.length
                ? `<table><thead><tr>${section.headings.map((heading) =>
                    `<th>${escapeHtml(heading)}</th>`
                ).join('')}</tr></thead><tbody>${section.rows.map((row) =>
                    `<tr>${row.map((cell, index) => {
                        const statusKind = section.headings[index] === 'Loan status'
                            ? reportStatusKind(cell)
                            : '';
                        return `<td${statusKind ? ` class="loan-status ${statusKind}"` : ''}>` +
                            `${escapeHtml(cell)}</td>`;
                    }).join('')}</tr>`
                ).join('')}</tbody></table>`
                : `<p>No ${escapeHtml(section.title.toLowerCase())} for this period.</p>`;
            return `<section><h2>${escapeHtml(section.title)}</h2>${content}</section>`;
        }).join('');
        printWindow.document.open();
        printWindow.document.write(
            '<!doctype html><html><head><meta charset="utf-8">' +
            `<title>${escapeHtml(report.title)}</title><style>` +
            'body{font:12px Arial,sans-serif;color:#24352b;margin:24px}' +
            'h1{font-size:20px}h2{font-size:16px;margin-top:24px}' +
            'table{width:100%;border-collapse:collapse;font-size:10px}' +
            'th,td{border:1px solid #b7c9bc;padding:6px;text-align:left;overflow-wrap:anywhere}' +
            '.loan-status{font-weight:700;-webkit-print-color-adjust:exact;print-color-adjust:exact}' +
            '.loan-status.current{color:#b42318;background:#fce8e6}' +
            '.loan-status.returned{color:#237a45;background:#e8f3eb}' +
            'th{background:#e8efe9}section{break-inside:avoid}' +
            '@media print{body{margin:12mm}}' +
            '</style></head><body>' +
            `<h1>${escapeHtml(report.title)}</h1>` +
            `<p>Generated ${escapeHtml(new Date().toLocaleString())}</p>` +
            sections +
            '<script>window.onload=()=>window.print();</script></body></html>'
        );
        printWindow.document.close();
        return true;
    }

    function saveReport() {
        const report = buildReport();
        const extension = reportFormatSelector?.value ?? 'txt';
        const filename = `library-report-${reportTypeSelector.value}-${new Date().toISOString().slice(0, 10)}.${extension}`;

        if (extension === 'txt') {
            downloadReport(new Blob([reportText(report)], {
                type: 'text/plain;charset=utf-8'
            }), filename);
            reportStatus.textContent = `Your report was downloaded as ${filename}.`;
        } else if (extension === 'docx') {
            downloadReport(makeDocx(report), filename);
            reportStatus.textContent = `Your report was downloaded as ${filename}.`;
        } else if (extension === 'pdf') {
            const opened = openPdfPrintDialog(report);
            reportStatus.textContent = opened
                ? 'Use the print dialog to save your report as a PDF.'
                : 'Allow pop-ups to open the print dialog and save your report as a PDF.';
            return opened;
        }

        return true;
    }

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
        if (charts && selected && Array.isArray(selected.points)) {
            charts.querySelectorAll('[data-series]').forEach((panel) => {
                const metric = panel.dataset.series;
                const svg = panel.querySelector('svg');
                if (svg && chartLabels[metric]) {
                    renderChart(svg, selected.points, metric, selected.label);
                }
            });
        }

        const period = overviewData[selector.value];
        if (period && period.stats) {
            const title = document.querySelector('#dashboard-period-title');
            if (title) title.textContent = period.label;
            document.querySelectorAll('[data-overview-stat]').forEach((value) => {
                const metric = value.dataset.overviewStat;
                if (Object.hasOwn(period.stats, metric)) {
                    value.textContent = numberFormatter.format(period.stats[metric]);
                }
            });
        }

        const fines = overviewData.fines?.[selector.value];
        if (fines) {
            document.querySelectorAll('[data-fine-stat]').forEach((value) => {
                const metric = value.dataset.fineStat;
                if (!Object.hasOwn(fines, metric)) return;
                value.textContent = ['assessed', 'paid', 'outstanding'].includes(metric)
                    ? currencyFormatter.format(fines[metric])
                    : numberFormatter.format(fines[metric]);
            });
            const note = document.querySelector('#fines-period-note');
            if (note) {
                note.textContent =
                    `Fines for ${period?.label.toLowerCase() ?? 'the selected period'}; grouped by return date (checkout date if not returned). Fines without a linked checkout date are excluded.`;
            }
        }

        renderRecentCheckouts();
    }

    function updateReportPeriod() {
        const period = reportTypeSelector.value === 'overdue'
            ? `Overdue loans checked out ${selector.selectedOptions[0]?.textContent.toLowerCase() ?? 'during the selected period'}`
            : `Due within 3 days (${reportData.today} through ${reportData.upcomingEnd})`;
        if (reportPeriod) reportPeriod.textContent = period;
    }

    selector.addEventListener('change', () => {
        render();
        updateReportPeriod();
    });
    reportButton?.addEventListener('click', () => {
        updateReportPeriod();
        reportDialog?.showModal();
    });
    reportTypeSelector?.addEventListener('change', updateReportPeriod);
    document.querySelector('#cancel-dashboard-report')?.addEventListener('click', () => {
        reportDialog?.close();
    });
    reportForm?.addEventListener('submit', (event) => {
        event.preventDefault();
        if (saveReport()) reportDialog?.close();
    });
    render();
})();
