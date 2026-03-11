document.addEventListener('DOMContentLoaded', () => {
    const cards = document.querySelectorAll('.card');
    const modalDetails = document.getElementById('modalDetails');

    const modal = document.querySelector('.modal');

    cards.forEach(card => {
        card.addEventListener('click', (event) => {
            if (event.target.closest('.card-dismiss-btn')) return;
            if (card.hasAttribute('data-no-data')) return;
            const slug = card.getAttribute('data-slug');
            const detailId = card.getAttribute('data-detail-id') || `detail-${slug}`;
            const sourceDetail = document.getElementById(detailId);

            if (sourceDetail && modalDetails) {
                const existingCharts = modalDetails.querySelectorAll('.modal-chart, canvas');
                existingCharts.forEach(el => {
                    if (el.chartInstance) {
                        if (typeof el.chartInstance.dispose === 'function') {
                            el.chartInstance.dispose();
                        } else if (typeof el.chartInstance.destroy === 'function') {
                            el.chartInstance.destroy();
                        }
                        el.chartInstance = null;
                    }
                });

                modalDetails.innerHTML = sourceDetail.innerHTML;

                const chartConfigJson = card.getAttribute('data-chart');
                if (chartConfigJson) {
                    try {
                        const config = JSON.parse(chartConfigJson);

                        const canvas = modalDetails.querySelector('.modal-chart, canvas');
                        if (canvas) {
                            const canvasId = canvas.dataset.id || config.target || `chart-${Date.now()}`;
                            canvas.id = canvasId;

                            const panelId = detailId.replace('detail-', 'panel-');
                            const modalPanel = modalDetails.querySelector('.modal-grid');
                            if (modalPanel && modalPanel.dataset.chart) {
                                config.type = modalPanel.dataset.chart;
                            }
                            setTimeout(() => {
                                if (typeof updatePanelChart === 'function') {
                                    updatePanelChart(panelId, canvasId, config.title);
                                } else if (typeof createChart === 'function') {
                                    createChart(
                                        config.type,
                                        config.title,
                                        config.labels,
                                        config.data,
                                        canvasId,
                                        config.color,
                                        config.thresholds,
                                        config.view
                                    );
                                }
                            }, 50);
                        }
                    } catch (e) {
                        console.error("Error parsing chart config", e);
                    }
                }
            }

            if (typeof toggleModal === 'function') {
                if (!modal.classList.contains('show-modal')) {
                    toggleModal();
                }
            } else {
                modal.classList.add('show-modal');
            }
        });
    });
});
