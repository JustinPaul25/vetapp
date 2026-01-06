<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import { watch } from 'vue';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Label } from '@/components/ui/label';
import { Switch } from '@/components/ui/switch';
import { Input } from '@/components/ui/input';
import { Button } from '@/components/ui/button';
import { Settings, BrainCircuit, UserCog } from 'lucide-vue-next';
import { dashboard } from '@/routes';

interface Setting {
    value: any;
    type: string;
    description: string;
}

interface Props {
    settings: {
        enable_knn_prediction?: Setting;
        enable_logistic_regression_prediction?: Setting;
        enable_neural_network_prediction?: Setting;
        veterinarian_name?: Setting;
        veterinarian_license_number?: Setting;
        [key: string]: Setting | undefined;
    };
}

const props = defineProps<Props>();

const breadcrumbs = [
    { title: 'Dashboard', href: dashboard().url },
    { title: 'Settings', href: '#' },
];

const form = useForm({
    enable_knn_prediction: props.settings.enable_knn_prediction?.value ?? true,
    enable_logistic_regression_prediction: props.settings.enable_logistic_regression_prediction?.value ?? true,
    enable_neural_network_prediction: props.settings.enable_neural_network_prediction?.value ?? true,
    veterinarian_name: props.settings.veterinarian_name?.value ?? '',
    veterinarian_license_number: props.settings.veterinarian_license_number?.value ?? '',
});

// Watch for prop changes to sync form state (e.g., after server updates)
watch(() => props.settings, (newSettings) => {
    // Only update if the value has actually changed to avoid unnecessary updates
    if (newSettings.enable_knn_prediction !== undefined && 
        form.enable_knn_prediction !== newSettings.enable_knn_prediction.value) {
        form.enable_knn_prediction = newSettings.enable_knn_prediction.value ?? true;
    }
    if (newSettings.enable_logistic_regression_prediction !== undefined && 
        form.enable_logistic_regression_prediction !== newSettings.enable_logistic_regression_prediction.value) {
        form.enable_logistic_regression_prediction = newSettings.enable_logistic_regression_prediction.value ?? true;
    }
    if (newSettings.enable_neural_network_prediction !== undefined && 
        form.enable_neural_network_prediction !== newSettings.enable_neural_network_prediction.value) {
        form.enable_neural_network_prediction = newSettings.enable_neural_network_prediction.value ?? true;
    }
    if (newSettings.veterinarian_name !== undefined && 
        form.veterinarian_name !== newSettings.veterinarian_name.value) {
        form.veterinarian_name = newSettings.veterinarian_name.value ?? '';
    }
    if (newSettings.veterinarian_license_number !== undefined && 
        form.veterinarian_license_number !== newSettings.veterinarian_license_number.value) {
        form.veterinarian_license_number = newSettings.veterinarian_license_number.value ?? '';
    }
}, { deep: true, immediate: false });

const updateSetting = (key: string, value: any) => {
    router.patch(
        '/admin/settings',
        {
            key,
            value,
        },
        {
            preserveScroll: true,
            preserveState: false,
            only: ['settings'],
            onError: () => {
                // Revert local state on error by reloading from props
                if (key === 'enable_knn_prediction') {
                    form.enable_knn_prediction = props.settings.enable_knn_prediction?.value ?? false;
                } else if (key === 'enable_logistic_regression_prediction') {
                    form.enable_logistic_regression_prediction = props.settings.enable_logistic_regression_prediction?.value ?? false;
                } else if (key === 'enable_neural_network_prediction') {
                    form.enable_neural_network_prediction = props.settings.enable_neural_network_prediction?.value ?? false;
                }
            },
        }
    );
};

const toggleKnnPrediction = (checked: boolean) => {
    form.enable_knn_prediction = checked;
    updateSetting('enable_knn_prediction', checked);
};

const toggleLogisticRegressionPrediction = (checked: boolean) => {
    form.enable_logistic_regression_prediction = checked;
    updateSetting('enable_logistic_regression_prediction', checked);
};

const toggleNeuralNetworkPrediction = (checked: boolean) => {
    form.enable_neural_network_prediction = checked;
    updateSetting('enable_neural_network_prediction', checked);
};

const saveVeterinarianInfo = () => {
    // Save both veterinarian settings using the same pattern as updateSetting
    // Make two separate PATCH requests like the toggles do
    updateSetting('veterinarian_name', form.veterinarian_name || '');
    
    // Use a small delay to ensure both requests are processed
    setTimeout(() => {
        updateSetting('veterinarian_license_number', form.veterinarian_license_number || '');
    }, 100);
};
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <Head title="Settings" />

        <div class="container mx-auto p-6">
            <div class="mb-6">
                <h1 class="text-3xl font-bold flex items-center gap-2">
                    <Settings class="h-8 w-8" />
                    System Settings
                </h1>
                <p class="text-muted-foreground mt-2">
                    Configure system-wide settings and preferences
                </p>
            </div>

            <div class="grid gap-6">
                <!-- Veterinarian Information -->
                <Card>
                    <CardHeader>
                        <CardTitle class="flex items-center gap-2">
                            <UserCog class="h-5 w-5" />
                            Veterinarian Information
                        </CardTitle>
                        <CardDescription>
                            Configure veterinarian details that will appear on prescription documents
                        </CardDescription>
                    </CardHeader>
                    <CardContent class="space-y-4">
                        <div class="space-y-2">
                            <Label for="veterinarian_name" class="text-base font-medium">
                                Veterinarian Name
                            </Label>
                            <Input
                                id="veterinarian_name"
                                v-model="form.veterinarian_name"
                                type="text"
                                placeholder="Enter veterinarian's full name"
                            />
                            <p class="text-sm text-muted-foreground">
                                {{ settings.veterinarian_name?.description }}
                            </p>
                        </div>

                        <div class="space-y-2">
                            <Label for="veterinarian_license_number" class="text-base font-medium">
                                License Number
                            </Label>
                            <Input
                                id="veterinarian_license_number"
                                v-model="form.veterinarian_license_number"
                                type="text"
                                placeholder="Enter veterinarian's license number"
                            />
                            <p class="text-sm text-muted-foreground">
                                {{ settings.veterinarian_license_number?.description }}
                            </p>
                        </div>

                        <div class="flex justify-end pt-4">
                            <Button @click="saveVeterinarianInfo">
                                Save Veterinarian Information
                            </Button>
                        </div>
                    </CardContent>
                </Card>

                <!-- KNN Prediction Setting -->
                <Card>
                    <CardHeader>
                        <CardTitle class="flex items-center gap-2">
                            <BrainCircuit class="h-5 w-5" />
                            Machine Learning Settings
                        </CardTitle>
                        <CardDescription>
                            Configure machine learning and prediction features
                        </CardDescription>
                    </CardHeader>
                    <CardContent class="space-y-6">
                        <!-- Neural Network Toggle -->
                        <div class="flex items-center justify-between space-x-4">
                            <div class="flex-1 space-y-1">
                                <Label for="enable_neural_network_prediction" class="text-base font-medium">
                                    Enable Neural Network Prediction
                                </Label>
                                <p class="text-sm text-muted-foreground">
                                    {{ settings.enable_neural_network_prediction?.description }}
                                </p>
                            </div>
                            <Switch
                                id="enable_neural_network_prediction"
                                :checked="form.enable_neural_network_prediction"
                                @update:checked="toggleNeuralNetworkPrediction"
                            />
                        </div>

                        <div
                            v-if="!form.enable_neural_network_prediction"
                            class="rounded-lg bg-yellow-50 dark:bg-yellow-900/20 p-4 border border-yellow-200 dark:border-yellow-800"
                        >
                            <p class="text-sm text-yellow-800 dark:text-yellow-200">
                                <strong>Note:</strong> When Neural Network is disabled, the system will not use
                                this advanced deep learning algorithm for predictions.
                            </p>
                        </div>

                        <div class="border-t pt-6">
                            <div class="flex items-center justify-between space-x-4">
                                <div class="flex-1 space-y-1">
                                    <Label for="enable_logistic_regression_prediction" class="text-base font-medium">
                                        Enable Logistic Regression Prediction
                                    </Label>
                                    <p class="text-sm text-muted-foreground">
                                        {{ settings.enable_logistic_regression_prediction?.description }}
                                    </p>
                                </div>
                                <Switch
                                    id="enable_logistic_regression_prediction"
                                    :checked="form.enable_logistic_regression_prediction"
                                    @update:checked="toggleLogisticRegressionPrediction"
                                />
                            </div>
                        </div>

                        <div
                            v-if="!form.enable_logistic_regression_prediction"
                            class="rounded-lg bg-yellow-50 dark:bg-yellow-900/20 p-4 border border-yellow-200 dark:border-yellow-800"
                        >
                            <p class="text-sm text-yellow-800 dark:text-yellow-200">
                                <strong>Note:</strong> When Logistic Regression is disabled, the system will not use
                                this algorithm to recommend medicines or predict diseases based on symptoms.
                            </p>
                        </div>

                        <div class="border-t pt-6">
                            <div class="flex items-center justify-between space-x-4">
                                <div class="flex-1 space-y-1">
                                    <Label for="enable_knn_prediction" class="text-base font-medium">
                                        Enable KNN Prediction
                                    </Label>
                                    <p class="text-sm text-muted-foreground">
                                        {{ settings.enable_knn_prediction?.description }}
                                    </p>
                                </div>
                                <Switch
                                    id="enable_knn_prediction"
                                    :checked="form.enable_knn_prediction"
                                    @update:checked="toggleKnnPrediction"
                                />
                            </div>
                        </div>

                        <div
                            v-if="!form.enable_knn_prediction"
                            class="rounded-lg bg-yellow-50 dark:bg-yellow-900/20 p-4 border border-yellow-200 dark:border-yellow-800"
                        >
                            <p class="text-sm text-yellow-800 dark:text-yellow-200">
                                <strong>Note:</strong> When KNN prediction is disabled, the system will not use
                                this algorithm to recommend medicines or predict diseases based on symptoms.
                            </p>
                        </div>

                        <div
                            v-if="!form.enable_knn_prediction && !form.enable_logistic_regression_prediction && !form.enable_neural_network_prediction"
                            class="rounded-lg bg-red-50 dark:bg-red-900/20 p-4 border border-red-200 dark:border-red-800"
                        >
                            <p class="text-sm text-red-800 dark:text-red-200">
                                <strong>Warning:</strong> All machine learning algorithms are disabled. 
                                Manual selection will be required for all disease diagnoses and medicine recommendations.
                            </p>
                        </div>

                        <div
                            v-if="form.enable_neural_network_prediction || form.enable_logistic_regression_prediction || form.enable_knn_prediction"
                            class="rounded-lg bg-blue-50 dark:bg-blue-900/20 p-4 border border-blue-200 dark:border-blue-800"
                        >
                            <p class="text-sm text-blue-800 dark:text-blue-200">
                                <strong>Algorithm Priority:</strong>
                                <span v-if="form.enable_neural_network_prediction && form.enable_logistic_regression_prediction && form.enable_knn_prediction">
                                    Neural Network → Logistic Regression → KNN. This provides the best accuracy with multiple fallback options.
                                </span>
                                <span v-else-if="form.enable_neural_network_prediction && form.enable_logistic_regression_prediction">
                                    Neural Network → Logistic Regression. High accuracy with fallback.
                                </span>
                                <span v-else-if="form.enable_neural_network_prediction && form.enable_knn_prediction">
                                    Neural Network → KNN. Advanced learning with reliable fallback.
                                </span>
                                <span v-else-if="form.enable_logistic_regression_prediction && form.enable_knn_prediction">
                                    Logistic Regression → KNN. Good accuracy with fallback.
                                </span>
                                <span v-else-if="form.enable_neural_network_prediction">
                                    Neural Network only. Best accuracy for complex patterns.
                                </span>
                                <span v-else-if="form.enable_logistic_regression_prediction">
                                    Logistic Regression only. Good pattern learning.
                                </span>
                                <span v-else-if="form.enable_knn_prediction">
                                    KNN only. Fast and reliable similarity-based predictions.
                                </span>
                            </p>
                        </div>
                    </CardContent>
                </Card>
            </div>
        </div>
    </AppLayout>
</template>
