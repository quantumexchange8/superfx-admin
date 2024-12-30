<script setup>
import Chart from 'chart.js/auto'
import {onMounted, ref, watch} from "vue";

const props = defineProps({
    selectedMonth: String,
    masterDetail: Object,
})

const emit = defineEmits(['update:monthlyGain'])

let chartInstance = null;
const month = ref(props.selectedMonth)
const chartData = ref({
    labels: [],
    datasets: [],
});

const fetchData = async () => {
    try {
        if (chartInstance) {
            chartInstance.destroy();
        }

        const ctx = document.getElementById('pnl_performance_chart');

        // isLoading.value = true;

        const response = await axios.get('/pamm_allocate/getMasterMonthlyProfit', { params: { master_id: props.masterDetail.id, month: month.value } });
        const { labels, datasets } = response.data.chartData;

        emit('update:monthlyGain', response.data.monthlyGain);

        chartData.value.labels = labels;
        chartData.value.datasets = datasets;

        // isLoading.value = false

        // Create the chart after updating chartData
        chartInstance = new Chart(ctx, {
            type: 'bar',
            data: chartData.value,
            options: {
                maintainAspectRatio: false,
                scales: {
                    y: {
                        ticks: {
                            color: '#667085',
                            font: {
                                family: 'Poppins, sans-serif',
                                size: 12,
                                weight: 400,
                            },
                        },
                        grace: '10%',
                        beginAtZero: true,
                        border: {
                            display: false
                        },
                        grid: {
                            drawTicks: false,
                            color: (ctx) => {
                                return '#F2F4F7'
                            }
                        },
                    },
                    x: {
                        ticks: {
                            color: '#667085',
                            font: {
                                family: 'Poppins, sans-serif',
                                size: 12,
                                weight: 400,
                            },
                        },
                        grid: {
                            drawTicks: false,
                            color: (ctx) => {
                                return 'transparent'
                            }
                        },
                    }
                },
                plugins: {
                    legend: {
                        display: false
                    },
                }
            }
        });
    } catch (error) {
        const ctx = document.getElementById('pnl_performance_chart');

        // isLoading.value = false
        console.error('Error fetching chart data:', error);
    }
}

onMounted(() => {
    fetchData();
});

watch(
    [() => props.selectedMonth, () => props.masterDetail],
    ([newMonth]) => {
        // This callback will be called when selectedMonth or selectedYear changes.
        month.value = newMonth;
        fetchData();
    }
);
</script>

<template>
    <div class="w-full">
      <canvas id="pnl_performance_chart" height="250"></canvas>
    </div>
</template>
