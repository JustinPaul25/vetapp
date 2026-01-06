<script setup lang="ts">
import { computed } from 'vue';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';

interface ConfusionMatrix {
    true_positives: number;
    false_positives: number;
    false_negatives: number;
    true_negatives: number;
}

interface ClassificationMetrics {
    precision: number;
    recall: number;
    f1_score: number;
    support: number;
}

interface Props {
    confusionMatrix: ConfusionMatrix;
    algorithm: string;
    accuracy: number;
    totalSamples: number;
    class0Label?: string;
    class1Label?: string;
}

const props = withDefaults(defineProps<Props>(), {
    class0Label: 'Not Eligible',
    class1Label: 'Eligible',
});

const algorithmName = computed(() => {
    return props.algorithm
        .split('_')
        .map((word) => word.charAt(0).toUpperCase() + word.slice(1))
        .join(' ');
});

// Calculate metrics for Class 0 (Negative/Not Eligible)
const class0Metrics = computed<ClassificationMetrics>(() => {
    const tp = props.confusionMatrix.true_negatives; // TN for class 0
    const fp = props.confusionMatrix.false_negatives; // FN for class 0
    const fn = props.confusionMatrix.false_positives; // FP for class 0
    const support = tp + fn; // Total actual negatives

    const precision = tp + fp > 0 ? tp / (tp + fp) : 0;
    const recall = tp + fn > 0 ? tp / (tp + fn) : 0;
    const f1Score = precision + recall > 0 ? (2 * precision * recall) / (precision + recall) : 0;

    return {
        precision: round(precision),
        recall: round(recall),
        f1_score: round(f1Score),
        support: support,
    };
});

// Calculate metrics for Class 1 (Positive/Eligible)
const class1Metrics = computed<ClassificationMetrics>(() => {
    const tp = props.confusionMatrix.true_positives;
    const fp = props.confusionMatrix.false_positives;
    const fn = props.confusionMatrix.false_negatives;
    const support = tp + fn; // Total actual positives

    const precision = tp + fp > 0 ? tp / (tp + fp) : 0;
    const recall = tp + fn > 0 ? tp / (tp + fn) : 0;
    const f1Score = precision + recall > 0 ? (2 * precision * recall) / (precision + recall) : 0;

    return {
        precision: round(precision),
        recall: round(recall),
        f1_score: round(f1Score),
        support: support,
    };
});

// Calculate macro average
const macroAvg = computed<ClassificationMetrics>(() => {
    return {
        precision: round((class0Metrics.value.precision + class1Metrics.value.precision) / 2),
        recall: round((class0Metrics.value.recall + class1Metrics.value.recall) / 2),
        f1_score: round((class0Metrics.value.f1_score + class1Metrics.value.f1_score) / 2),
        support: class0Metrics.value.support + class1Metrics.value.support,
    };
});

// Calculate weighted average
const weightedAvg = computed<ClassificationMetrics>(() => {
    const totalSupport = class0Metrics.value.support + class1Metrics.value.support;
    if (totalSupport === 0) {
        return {
            precision: 0,
            recall: 0,
            f1_score: 0,
            support: 0,
        };
    }

    const weight0 = class0Metrics.value.support / totalSupport;
    const weight1 = class1Metrics.value.support / totalSupport;

    return {
        precision: round(
            class0Metrics.value.precision * weight0 + class1Metrics.value.precision * weight1
        ),
        recall: round(
            class0Metrics.value.recall * weight0 + class1Metrics.value.recall * weight1
        ),
        f1_score: round(
            class0Metrics.value.f1_score * weight0 + class1Metrics.value.f1_score * weight1
        ),
        support: totalSupport,
    };
});

const overallAccuracy = computed(() => {
    return round(props.accuracy / 100); // Convert from percentage to decimal
});

const correctPredictions = computed(() => {
    return Math.round(overallAccuracy.value * props.totalSamples);
});

const class0PrecisionPercent = computed(() => {
    return Math.round(class0Metrics.value.precision * 100);
});

const class0RecallPercent = computed(() => {
    return Math.round(class0Metrics.value.recall * 100);
});

const class1PrecisionPercent = computed(() => {
    return Math.round(class1Metrics.value.precision * 100);
});

const class1RecallPercent = computed(() => {
    return Math.round(class1Metrics.value.recall * 100);
});

function round(value: number): number {
    return Math.round(value * 100) / 100;
}

function formatMetric(value: number): string {
    return value.toFixed(2);
}
</script>

<template>
    <Card>
        <CardHeader>
            <CardTitle>{{ algorithmName }} Classification Report</CardTitle>
        </CardHeader>
        <CardContent class="space-y-6">
            <!-- Overall Accuracy Explanation -->
            <div class="bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800 rounded-lg p-4">
                <h3 class="font-semibold text-lg mb-2 text-blue-900 dark:text-blue-100">
                    Overall Accuracy: {{ (overallAccuracy * 100).toFixed(2) }}%
                </h3>
                <p class="text-sm text-blue-800 dark:text-blue-200">
                    The model correctly predicted the eligibility of
                    <strong>{{ correctPredictions }}</strong> out of
                    <strong>{{ totalSamples }}</strong> employees in the test set.
                </p>
                <p class="text-sm text-blue-800 dark:text-blue-200 mt-2">
                    While high accuracy is good, more detailed metrics are needed to understand
                    performance for each class (0 = {{ class0Label }}, 1 = {{ class1Label }}).
                </p>
            </div>

            <!-- Classification Report Table -->
            <div class="overflow-x-auto">
                <table class="w-full border-collapse border border-gray-300 dark:border-gray-700">
                    <thead>
                        <tr class="bg-gray-100 dark:bg-gray-800">
                            <th class="border border-gray-300 dark:border-gray-700 p-3 text-left font-semibold">
                            </th>
                            <th class="border border-gray-300 dark:border-gray-700 p-3 text-center font-semibold">
                                precision
                            </th>
                            <th class="border border-gray-300 dark:border-gray-700 p-3 text-center font-semibold">
                                recall
                            </th>
                            <th class="border border-gray-300 dark:border-gray-700 p-3 text-center font-semibold">
                                f1-score
                            </th>
                            <th class="border border-gray-300 dark:border-gray-700 p-3 text-center font-semibold">
                                support
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        <!-- Class 0 -->
                        <tr class="hover:bg-gray-50 dark:hover:bg-gray-800/50">
                            <td class="border border-gray-300 dark:border-gray-700 p-3 font-semibold">
                                0
                            </td>
                            <td class="border border-gray-300 dark:border-gray-700 p-3 text-center">
                                {{ formatMetric(class0Metrics.precision) }}
                            </td>
                            <td class="border border-gray-300 dark:border-gray-700 p-3 text-center">
                                {{ formatMetric(class0Metrics.recall) }}
                            </td>
                            <td class="border border-gray-300 dark:border-gray-700 p-3 text-center">
                                {{ formatMetric(class0Metrics.f1_score) }}
                            </td>
                            <td class="border border-gray-300 dark:border-gray-700 p-3 text-center">
                                {{ class0Metrics.support }}
                            </td>
                        </tr>
                        <!-- Class 1 -->
                        <tr class="hover:bg-gray-50 dark:hover:bg-gray-800/50">
                            <td class="border border-gray-300 dark:border-gray-700 p-3 font-semibold">
                                1
                            </td>
                            <td class="border border-gray-300 dark:border-gray-700 p-3 text-center">
                                {{ formatMetric(class1Metrics.precision) }}
                            </td>
                            <td class="border border-gray-300 dark:border-gray-700 p-3 text-center">
                                {{ formatMetric(class1Metrics.recall) }}
                            </td>
                            <td class="border border-gray-300 dark:border-gray-700 p-3 text-center">
                                {{ formatMetric(class1Metrics.f1_score) }}
                            </td>
                            <td class="border border-gray-300 dark:border-gray-700 p-3 text-center">
                                {{ class1Metrics.support }}
                            </td>
                        </tr>
                        <!-- Accuracy -->
                        <tr class="bg-gray-50 dark:bg-gray-800/30">
                            <td class="border border-gray-300 dark:border-gray-700 p-3 font-semibold">
                                accuracy
                            </td>
                            <td class="border border-gray-300 dark:border-gray-700 p-3 text-center" colspan="3">
                                {{ formatMetric(overallAccuracy) }}
                            </td>
                            <td class="border border-gray-300 dark:border-gray-700 p-3 text-center">
                                {{ totalSamples }}
                            </td>
                        </tr>
                        <!-- Macro Avg -->
                        <tr class="bg-gray-50 dark:bg-gray-800/30">
                            <td class="border border-gray-300 dark:border-gray-700 p-3 font-semibold">
                                macro avg
                            </td>
                            <td class="border border-gray-300 dark:border-gray-700 p-3 text-center">
                                {{ formatMetric(macroAvg.precision) }}
                            </td>
                            <td class="border border-gray-300 dark:border-gray-700 p-3 text-center">
                                {{ formatMetric(macroAvg.recall) }}
                            </td>
                            <td class="border border-gray-300 dark:border-gray-700 p-3 text-center">
                                {{ formatMetric(macroAvg.f1_score) }}
                            </td>
                            <td class="border border-gray-300 dark:border-gray-700 p-3 text-center">
                                {{ macroAvg.support }}
                            </td>
                        </tr>
                        <!-- Weighted Avg -->
                        <tr class="bg-gray-50 dark:bg-gray-800/30">
                            <td class="border border-gray-300 dark:border-gray-700 p-3 font-semibold">
                                weighted avg
                            </td>
                            <td class="border border-gray-300 dark:border-gray-700 p-3 text-center">
                                {{ formatMetric(weightedAvg.precision) }}
                            </td>
                            <td class="border border-gray-300 dark:border-gray-700 p-3 text-center">
                                {{ formatMetric(weightedAvg.recall) }}
                            </td>
                            <td class="border border-gray-300 dark:border-gray-700 p-3 text-center">
                                {{ formatMetric(weightedAvg.f1_score) }}
                            </td>
                            <td class="border border-gray-300 dark:border-gray-700 p-3 text-center">
                                {{ weightedAvg.support }}
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Class 0 Detailed Explanation -->
            <div class="bg-gray-50 dark:bg-gray-800/30 border border-gray-200 dark:border-gray-700 rounded-lg p-4">
                <h4 class="font-semibold text-base mb-3">Class 0 ({{ class0Label }}) Detailed Explanation</h4>
                <div class="space-y-2 text-sm">
                    <div>
                        <strong>Precision:</strong> {{ formatMetric(class0Metrics.precision) }} - "Out of all
                        predicted '{{ class0Label }}', {{ class0PrecisionPercent }}% were
                        {{ class0Label.toLowerCase() }}."
                    </div>
                    <div>
                        <strong>Recall:</strong> {{ formatMetric(class0Metrics.recall) }} - "Out of all truly
                        {{ class0Label.toLowerCase() }}, the model caught
                        {{ class0RecallPercent }}%."
                    </div>
                    <div>
                        <strong>F1-Score:</strong> {{ formatMetric(class0Metrics.f1_score) }} - "Balance
                        between precision and recall."
                    </div>
                    <div>
                        <strong>Support:</strong> {{ class0Metrics.support }} - "There were
                        {{ class0Metrics.support }} employees in the test set who were
                        {{ class0Label.toLowerCase() }}."
                    </div>
                </div>
            </div>

            <!-- Class 1 Detailed Explanation -->
            <div class="bg-gray-50 dark:bg-gray-800/30 border border-gray-200 dark:border-gray-700 rounded-lg p-4">
                <h4 class="font-semibold text-base mb-3">Class 1 ({{ class1Label }}) Detailed Explanation</h4>
                <div class="space-y-2 text-sm">
                    <div>
                        <strong>Precision:</strong> {{ formatMetric(class1Metrics.precision) }} - "Out of all
                        predicted '{{ class1Label }}', {{ class1PrecisionPercent }}% were
                        truly {{ class1Label.toLowerCase() }}."
                    </div>
                    <div>
                        <strong>Recall:</strong> {{ formatMetric(class1Metrics.recall) }} - "Out of all truly
                        {{ class1Label.toLowerCase() }} employees, {{ class1RecallPercent }}%
                        were correctly identified."
                    </div>
                    <div>
                        <strong>F1-Score:</strong> {{ formatMetric(class1Metrics.f1_score) }} - "Very strong
                        performance overall."
                    </div>
                    <div>
                        <strong>Support:</strong> {{ class1Metrics.support }} - "There were
                        {{ class1Metrics.support }} {{ class1Label.toLowerCase() }} employees in the test set."
                    </div>
                </div>
            </div>
        </CardContent>
    </Card>
</template>

