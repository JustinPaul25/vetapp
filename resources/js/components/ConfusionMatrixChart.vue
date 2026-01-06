<script setup lang="ts">
import { computed } from 'vue';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';

interface ConfusionMatrix {
    true_positives: number;
    false_positives: number;
    false_negatives: number;
    true_negatives: number;
}

interface Props {
    confusionMatrix: ConfusionMatrix;
    algorithm: string;
}

const props = defineProps<Props>();

const algorithmName = computed(() => {
    return props.algorithm
        .split('_')
        .map((word) => word.charAt(0).toUpperCase() + word.slice(1))
        .join(' ');
});

const total = computed(() => {
    return (
        props.confusionMatrix.true_positives +
        props.confusionMatrix.false_positives +
        props.confusionMatrix.false_negatives +
        props.confusionMatrix.true_negatives
    );
});

const getPercentage = (value: number) => {
    if (total.value === 0) return 0;
    return ((value / total.value) * 100).toFixed(1);
};
</script>

<template>
    <Card>
        <CardHeader>
            <CardTitle>{{ algorithmName }} - Confusion Matrix</CardTitle>
        </CardHeader>
        <CardContent>
            <div class="space-y-4">
                <!-- Confusion Matrix Table -->
                <div class="overflow-x-auto">
                    <table class="w-full border-collapse">
                        <thead>
                            <tr>
                                <th class="border p-2 text-center"></th>
                                <th class="border p-2 text-center bg-green-100 dark:bg-green-900/20">
                                    Predicted: Positive
                                </th>
                                <th class="border p-2 text-center bg-red-100 dark:bg-red-900/20">
                                    Predicted: Negative
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td
                                    class="border p-2 text-center font-semibold bg-green-100 dark:bg-green-900/20"
                                >
                                    Actual: Positive
                                </td>
                                <td
                                    class="border p-2 text-center bg-green-50 dark:bg-green-900/10"
                                >
                                    <div class="font-bold text-green-700 dark:text-green-400">
                                        TP: {{ confusionMatrix.true_positives }}
                                    </div>
                                    <div class="text-sm text-gray-600 dark:text-gray-400">
                                        {{ getPercentage(confusionMatrix.true_positives) }}%
                                    </div>
                                </td>
                                <td
                                    class="border p-2 text-center bg-red-50 dark:bg-red-900/10"
                                >
                                    <div class="font-bold text-red-700 dark:text-red-400">
                                        FN: {{ confusionMatrix.false_negatives }}
                                    </div>
                                    <div class="text-sm text-gray-600 dark:text-gray-400">
                                        {{ getPercentage(confusionMatrix.false_negatives) }}%
                                    </div>
                                </td>
                            </tr>
                            <tr>
                                <td
                                    class="border p-2 text-center font-semibold bg-red-100 dark:bg-red-900/20"
                                >
                                    Actual: Negative
                                </td>
                                <td
                                    class="border p-2 text-center bg-red-50 dark:bg-red-900/10"
                                >
                                    <div class="font-bold text-red-700 dark:text-red-400">
                                        FP: {{ confusionMatrix.false_positives }}
                                    </div>
                                    <div class="text-sm text-gray-600 dark:text-gray-400">
                                        {{ getPercentage(confusionMatrix.false_positives) }}%
                                    </div>
                                </td>
                                <td
                                    class="border p-2 text-center bg-green-50 dark:bg-green-900/10"
                                >
                                    <div class="font-bold text-green-700 dark:text-green-400">
                                        TN: {{ confusionMatrix.true_negatives }}
                                    </div>
                                    <div class="text-sm text-gray-600 dark:text-gray-400">
                                        {{ getPercentage(confusionMatrix.true_negatives) }}%
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Legend -->
                <div class="grid grid-cols-2 gap-2 text-sm">
                    <div class="flex items-center gap-2">
                        <div class="w-4 h-4 bg-green-500 rounded"></div>
                        <span>TP (True Positive): Correctly predicted positive</span>
                    </div>
                    <div class="flex items-center gap-2">
                        <div class="w-4 h-4 bg-red-500 rounded"></div>
                        <span>FP (False Positive): Incorrectly predicted positive</span>
                    </div>
                    <div class="flex items-center gap-2">
                        <div class="w-4 h-4 bg-red-500 rounded"></div>
                        <span>FN (False Negative): Incorrectly predicted negative</span>
                    </div>
                    <div class="flex items-center gap-2">
                        <div class="w-4 h-4 bg-green-500 rounded"></div>
                        <span>TN (True Negative): Correctly predicted negative</span>
                    </div>
                </div>

                <!-- Summary Stats -->
                <div class="pt-2 border-t">
                    <div class="text-sm text-gray-600 dark:text-gray-400">
                        <strong>Total Samples:</strong> {{ total.toLocaleString() }}
                    </div>
                </div>
            </div>
        </CardContent>
    </Card>
</template>

