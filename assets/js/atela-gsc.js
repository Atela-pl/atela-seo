/**
 * Atela SEO Premium - GSC Integration via Google Site Kit
 */

document.addEventListener('DOMContentLoaded', function() {
    const gscGlobalContainer = document.getElementById('atela-gsc-global-dashboard');
    const gscPostContainer = document.getElementById('atela-gsc-post-dashboard');

    if (!gscGlobalContainer && !gscPostContainer) {
        return; // Nie jesteśmy na stronie of widgetami GSC
    }

    // Funkcja do formatowania dat na YYYY-MM-DD
    function formatDate(date) {
        return date.toISOString().split('T')[0];
    }

    const endDate = new Date();
    const startDate = new Date();
    startDate.setDate(endDate.getDate() - 28); // Domyślnie 28 dni

    const strStartDate = formatDate(startDate);
    const strEndDate = formatDate(endDate);

    /**
     * Renderuje wykres dla danych of GSC (Clicks, Impressions).
     */
    function renderChart(container, data) {
        if (!data || data.length === 0) {
            container.innerHTML = '<p style="color:#666; padding: 20px 0;">No data to display for the selected period.</p>';
            return;
        }

        // Będziemy używać prostego, wbudowanego w HTML rozwiązania dla wykresu
        // Aby uniknąć ładowania dużych zewnętrznych bibliotek, wygenerujemy prosty bar chart HTML/CSS.
        
        let maxClicks = 0;
        let maxImpressions = 0;
        let totalClicks = 0;
        let totalImpressions = 0;
        
        data.forEach(row => {
            if (row.clicks > maxClicks) maxClicks = row.clicks;
            if (row.impressions > maxImpressions) maxImpressions = row.impressions;
            totalClicks += row.clicks;
            totalImpressions += row.impressions;
        });

        const avgCtr = totalImpressions > 0 ? ((totalClicks / totalImpressions) * 100).toFixed(2) : 0;

        // Podsumowanie
        let html = `
            <div style="display: flex; gap: 20px; margin-bottom: 20px;">
                <div style="background:#fff; border:1px solid #ddd; padding: 15px; border-radius: 4px; flex: 1; text-align: center;">
                    <div style="font-size: 13px; color: #666; text-transform: uppercase;">Clicks</div>
                    <div style="font-size: 28px; font-weight: bold; color: #1a73e8;">${totalClicks}</div>
                </div>
                <div style="background:#fff; border:1px solid #ddd; padding: 15px; border-radius: 4px; flex: 1; text-align: center;">
                    <div style="font-size: 13px; color: #666; text-transform: uppercase;">Impressions</div>
                    <div style="font-size: 28px; font-weight: bold; color: #5f6368;">${totalImpressions}</div>
                </div>
                <div style="background:#fff; border:1px solid #ddd; padding: 15px; border-radius: 4px; flex: 1; text-align: center;">
                    <div style="font-size: 13px; color: #666; text-transform: uppercase;">Avg CTR</div>
                    <div style="font-size: 28px; font-weight: bold; color: #34a853;">${avgCtr}%</div>
                </div>
            </div>
        `;

        // Tabela
        html += `<table class="wp-list-table widefat fixed striped" style="margin-top: 20px;">
            <thead>
                <tr>
                    <th>Data</th>
                    <th>Clicks</th>
                    <th>Impressions</th>
                    <th>CTR</th>
                    <th>Avg Position</th>
                </tr>
            </thead>
            <tbody>
        `;

        // Odwróć, by nowsze daty były na górze, albo zachowaj strukturę (często Site Kit oddaje posortowane datą)
        const sortedData = [...data].sort((a,b) => (a.keys[0] > b.keys[0] ? -1 : 1));

        sortedData.forEach(row => {
            const dateStr = row.keys[0]; // data jest w pierwszym wymiarze
            const ctr = (row.ctr * 100).toFixed(2);
            const pos = row.position.toFixed(1);
            
            html += `
                <tr>
                    <td><strong>${dateStr}</strong></td>
                    <td><span style="color:#1a73e8; font-weight:600;">${row.clicks}</span></td>
                    <td>${row.impressions}</td>
                    <td>${ctr}%</td>
                    <td>${pos}</td>
                </tr>
            `;
        });
        
        html += `</tbody></table>`;
        container.innerHTML = html;
    }

    /**
     * Renderuje tabelę ze słowami kluczowymi (Top 5)
     */
    function renderKeywordsTable(container, data, focusKeyword = '') {
        if (!data || data.length === 0) {
            container.innerHTML = '<p style="color:#666;">Brak danych o zapytaniach.</p>';
            return;
        }

        // Limit do top 5
        const top5 = data.slice(0, 5);

        let html = `
        <div style="background:#fff; border:1px solid #e2e8f0; border-radius: 8px; box-shadow: 0 1px 2px rgba(0,0,0,0.05); padding: 24px; margin-top: 20px;">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                <h3 style="margin: 0; font-size: 18px; font-weight: 600; color: #1e293b;">Top 5 searched queries</h3>
                <span title="Dane pobrane of Google Search Console" style="color: #94a3b8; cursor: help;">ⓘ</span>
            </div>
            
            <table style="width: 100%; border-collapse: collapse; text-align: left; font-size: 14px; color: #334155;">
                <thead>
                    <tr style="border-bottom: 1px solid #e2e8f0;">
                        <th style="padding: 12px 8px; font-weight: 600; color: #0f172a; width: 40px;"></th>
                        <th style="padding: 12px 8px; font-weight: 600; color: #0f172a;">Zapytanie</th>
                        <th style="padding: 12px 8px; font-weight: 600; color: #0f172a; text-align: right;">Clicks</th>
                        <th style="padding: 12px 8px; font-weight: 600; color: #0f172a; text-align: right;">Impressions</th>
                        <th style="padding: 12px 8px; font-weight: 600; color: #0f172a; text-align: right;">CTR</th>
                        <th style="padding: 12px 8px; font-weight: 600; color: #0f172a; text-align: right;">Avg Position</th>
                    </tr>
                </thead>
                <tbody>
        `;

        top5.forEach((row, index) => {
            const query = row.keys[0];
            const isFocus = focusKeyword && query.toLowerCase().includes(focusKeyword.toLowerCase());
            const ctr = (row.ctr * 100).toFixed(2);
            const pos = row.position.toFixed(2).replace('.', ',');

            const highlightStyle = isFocus ? 'background-color: #f0fdf4;' : '';
            const borderStyle = index < top5.length - 1 ? 'border-bottom: 1px solid #f1f5f9;' : '';

            html += `
                <tr style="${highlightStyle} ${borderStyle}">
                    <td style="padding: 12px 8px; color: #94a3b8;">${index + 1}.</td>
                    <td style="padding: 12px 8px;">
                        <strong>${query}</strong>
                        ${isFocus ? '<span style="color: #166534; font-size: 11px; margin-left: 8px; border: 1px solid #bbf7d0; padding: 2px 6px; border-radius: 4px; background: #dcfce7; font-weight:500;">Focus Keyword</span>' : ''}
                    </td>
                    <td style="padding: 12px 8px; text-align: right; color: #64748b;">${row.clicks}</td>
                    <td style="padding: 12px 8px; text-align: right; color: #64748b;">${row.impressions}</td>
                    <td style="padding: 12px 8px; text-align: right; color: #64748b;">${ctr}%</td>
                    <td style="padding: 12px 8px; text-align: right; color: #64748b;">${pos}</td>
                </tr>
            `;
        });

        html += `</tbody></table></div>`;
        container.innerHTML = html;
    }

    /**
     * Główny funkcja pobierająca dane
     */
    function fetchGscData(type, url = '', containerId, resultType = 'chart', focusKeyword = '') {
        const container = document.getElementById(containerId);
        if (!container) return;
        
        container.innerHTML = '<div style="padding: 20px 0; color: #666;"><span class="spinner is-active" style="float:none; margin-right: 5px;"></span> Pobieranie danych of Google Site Kit...</div>';

        if (!window.wp || !wp.apiFetch) {
            container.innerHTML = '<p style="color:#d63638; font-weight:bold;">Error: wp.apiFetch is unavailable. Try refreshing the page.</p>';
            return;
        }

        // Endpoint Google Site Kit dla GSC
        let apiPath = `/google-site-kit/v1/modules/search-console/data/searchanalytics?startDate=${strStartDate}&endDate=${strEndDate}`;
        
        if (type === 'date') {
            apiPath += '&dimensions=date';
        } else if (type === 'query') {
            apiPath += '&dimensions=query';
        }

        if (url) {
            apiPath += `&url=${encodeURIComponent(url)}`;
        }

        wp.apiFetch({ path: apiPath }).then(response => {
            if (resultType === 'chart') {
                renderChart(container, response);
            } else if (resultType === 'keywords') {
                renderKeywordsTable(container, response, focusKeyword);
            }
        }).catch(err => {
            console.error('Error GSC API:', err);
            let errMsg = (typeof atelaGscL10n !== 'undefined') ? atelaGscL10n.fetchError : 'An error occurred while fetching data.';
            if (err.code === 'rest_no_route') {
                errMsg = 'Google Site Kit nie jest zainstalowany lub autoryzowany. Upewnij się, że wtyczka jest aktywna i połączona of kontem Google.';
            } else if (err.message) {
                errMsg += ' ' + err.message;
            }
            container.innerHTML = `<div class="notice notice-error inline"><p>${errMsg}</p></div>`;
        });
    }

    // Inicjalizacja dla strony raportu ogólnego (zakładka GSC w menu)
    if (gscGlobalContainer) {
        fetchGscData('date', '', 'atela-gsc-global-dates', 'chart');
        fetchGscData('query', '', 'atela-gsc-global-queries', 'keywords');
    }

    // Inicjalizacja dla Meta Boxa w edycji posta
    if (gscPostContainer) {
        const postUrl = gscPostContainer.getAttribute('data-url');
        const focusKeyword = gscPostContainer.getAttribute('data-focus') || '';
        
        if (postUrl) {
            // Najpierw statystyki datowe dla danego wpisu
            fetchGscData('date', postUrl, 'atela-gsc-post-dates', 'chart');
            // Następnie słowa kluczowe dla danego wpisu
            fetchGscData('query', postUrl, 'atela-gsc-post-queries', 'keywords', focusKeyword);
        } else {
            gscPostContainer.innerHTML = '<p>No URL to analyze. Make sure the post is published.</p>';
        }
    }
});
