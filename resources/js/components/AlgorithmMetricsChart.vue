<script setup lang="ts">
import { computed } from 'vue';
import { Bar } from 'vue-chartjs';
import {
    Chart as ChartJS,
    CategoryScale,
    LinearScale,
    BarElement,
    Title,
    Tooltip,
    Legend,
} from 'chart.js';

// Register Chart.js components
ChartJS.register(
    CategoryScale,
    LinearScale,
    BarElement,
    Title,
    Tooltip,
    Legend
);

interface AlgorithmMetrics {
    algorithm: string;
    accuracy: number;
    precision: number;
    recall: number;
    f1_score: number;
    confusion_matrix: {
        true_positives: number;
        false_positives: number;
        false_negatives: number;
        true_negatives: number;
    };
    total_samples: number;
}

interface Props {
    metrics: AlgorithmMetrics;
}

const props = defineProps<Props>();

const chartData = computed(() => {
    const algorithmName = props.metrics.algorithm
        .split('_')
        .map((word) => word.charAt(0).toUpperCase() + word.slice(1))
        .join(' ');

    return {
        labels: ['Accuracy', 'Precision', 'Recall', 'F1-Score'],
        datasets: [
            {
                label: algorithmName,
                backgroundColor: [
                    'rgba(59, 130, 246, 0.8)', // Blue for Accuracy
                    'rgba(16, 185, 129, 0.8)', // Green for Precision
                    'rgba(245, 158, 11, 0.8)', // Orange for Recall
                    'rgba(139, 92, 246, 0.8)', // Purple for F1-Score
                ],
                borderColor: [
                    'rgba(59, 130, 246, 1)',
                    'rgba(16, 185, 129, 1)',
                    'rgba(245, 158, 11, 1)',
                    'rgba(139, 92, 246, 1)',
                ],
                borderWidth: 1,
                data: [
                    props.metrics.accuracy,
                    props.metrics.precision,
                    props.metrics.recall,
                    props.metrics.f1_score,
                ],
            },
        ],
    };
});

const chartOptions = {
    responsive: true,
    maintainAspectRatio: false,
    plugins: {
        legend: {
            display: false,
        },
        title: {
            display: true,
            text: props.metrics.algorithm
                .split('_')
                .map((word) => word.charAt(0).toUpperCase() + word.slice(1))
                .join(' ') + ' Metrics',
            font: {
                size: 16,
                weight: 'bold' as const,
            },
        },
        tooltip: {
            callbacks: {
                label: function (context: any) {
                    return `${context.label}: ${context.parsed.y.toFixed(2)}%`;
                },
            },
        },
    },
    scales: {
        y: {
            beginAtZero: true,
            max: 100,
            ticks: {
                callback: function (value: any) {
                    return value + '%';
                },
            },
        },
    },
};
</script>

<template>
    <div class="w-full h-64">
        <Bar :data="chartData" :options="chartOptions" />
    </div>
</template>

