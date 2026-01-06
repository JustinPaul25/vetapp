<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import { watch, ref, computed } from 'vue';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Label } from '@/components/ui/label';
import { Switch } from '@/components/ui/switch';
import { Input } from '@/components/ui/input';
import { Button } from '@/components/ui/button';
import { Settings, BrainCircuit, UserCog, BarChart3 } from 'lucide-vue-next';
import { dashboard } from '@/routes';
import axios from 'axios';
import AlgorithmMetricsChart from '@/components/AlgorithmMetricsChart.vue';
import ConfusionMatrixChart from '@/components/ConfusionMatrixChart.vue';
import ClassificationReport from '@/components/ClassificationReport.vue';
import { Tabs, TabsList, TabsTrigger, TabsContent } from '@/components/ui/tabs';
import { useToast } from '@/composables/useToast';

interface Setting {
    value: any;
    type: string;
    description: string;
}

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
    settings: {
        enable_knn_prediction?: Setting;
        enable_logistic_regression_prediction?: Setting;
        enable_neural_network_prediction?: Setting;
        selected_ml_algorithm?: Setting;
        veterinarian_name?: Setting;
        veterinarian_license_number?: Setting;
        [key: string]: Setting | undefined;
    };
    algorithmMetrics?: {
        neural_network?: AlgorithmMetrics;
        logistic_regression?: AlgorithmMetrics;
        knn?: AlgorithmMetrics;
    };
}

const props = defineProps<Props>();

const { success: showSuccess, error: showError } = useToast();

// Tab state for algorithm metrics
const activeAlgorithmTab = ref('neural_network');

// Available algorithms
const availableAlgorithms = computed(() => {
    const algorithms = [];
    if (props.algorithmMetrics?.neural_network) {
        algorithms.push({ value: 'neural_network', label: 'Neural Network' });
    }
    if (props.algorithmMetrics?.logistic_regression) {
        algorithms.push({ value: 'logistic_regression', label: 'Logistic Regression' });
    }
    if (props.algorithmMetrics?.knn) {
        algorithms.push({ value: 'knn', label: 'K-Nearest Neighbors (KNN)' });
    }
    
    // Set default tab to first available algorithm
    if (algorithms.length > 0 && !algorithms.find(a => a.value === activeAlgorithmTab.value)) {
        activeAlgorithmTab.value = algorithms[0].value;
    }
    
    return algorithms;
});

// Get current algorithm metrics
const currentMetrics = computed(() => {
    if (!props.algorithmMetrics) return null;
    return props.algorithmMetrics[activeAlgorithmTab.value as keyof typeof props.algorithmMetrics] || null;
});

const breadcrumbs = [
    { title: 'Dashboard', href: dashboard().url },
    { title: 'Settings', href: '#' },
];

// Helper function to safely get boolean setting value
const getBooleanSetting = (setting: Setting | undefined, defaultValue: boolean = true): boolean => {
    if (setting === undefined) return defaultValue;
    const value = setting.value;
    // Handle various boolean representations
    if (typeof value === 'boolean') return value;
    if (value === 'true' || value === '1' || value === 1) return true;
    if (value === 'false' || value === '0' || value === 0 || value === null || value === '') return false;
    return defaultValue;
};

const form = useForm({
    enable_knn_prediction: getBooleanSetting(props.settings.enable_knn_prediction, true),
    enable_logistic_regression_prediction: getBooleanSetting(props.settings.enable_logistic_regression_prediction, true),
    enable_neural_network_prediction: getBooleanSetting(props.settings.enable_neural_network_prediction, true),
    selected_ml_algorithm: props.settings.selected_ml_algorithm?.value ?? 'neural_network',
    veterinarian_name: props.settings.veterinarian_name?.value ?? '',
    veterinarian_license_number: props.settings.veterinarian_license_number?.value ?? '',
});

// Watch for prop changes to sync form state (e.g., after server updates)
watch(() => props.settings, (newSettings) => {
    // Only update if the value has actually changed to avoid unnecessary updates
    if (newSettings.enable_knn_prediction !== undefined) {
        const newValue = getBooleanSetting(newSettings.enable_knn_prediction, true);
        if (form.enable_knn_prediction !== newValue) {
            form.enable_knn_prediction = newValue;
        }
    }
    if (newSettings.enable_logistic_regression_prediction !== undefined) {
        const newValue = getBooleanSetting(newSettings.enable_logistic_regression_prediction, true);
        if (form.enable_logistic_regression_prediction !== newValue) {
            form.enable_logistic_regression_prediction = newValue;
        }
    }
    if (newSettings.enable_neural_network_prediction !== undefined) {
        const newValue = getBooleanSetting(newSettings.enable_neural_network_prediction, true);
        if (form.enable_neural_network_prediction !== newValue) {
            form.enable_neural_network_prediction = newValue;
        }
    }
    if (newSettings.selected_ml_algorithm !== undefined && 
        form.selected_ml_algorithm !== newSettings.selected_ml_algorithm.value) {
        form.selected_ml_algorithm = newSettings.selected_ml_algorithm.value ?? 'neural_network';
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

const updateSetting = async (key: string, value: any) => {
    console.log('updateSetting called:', { key, value });
    
    try {
        // Use axios for more reliable requests
        const response = await axios.patch(
            '/admin/settings',
            {
                key,
                value,
            },
            {
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'application/json',
                    'Content-Type': 'application/json',
                },
            }
        );
        
        console.log('Setting updated successfully:', key, value, response.data);
        
        // Reload settings using Inertia to update props
        router.reload({ only: ['settings'] });
    } catch (error: any) {
        console.error('Error updating setting:', error);
        
        // Revert local state on error by reloading from props
        if (key === 'enable_knn_prediction') {
            form.enable_knn_prediction = getBooleanSetting(props.settings.enable_knn_prediction, true);
        } else if (key === 'enable_logistic_regression_prediction') {
            form.enable_logistic_regression_prediction = getBooleanSetting(props.settings.enable_logistic_regression_prediction, true);
        } else if (key === 'enable_neural_network_prediction') {
            form.enable_neural_network_prediction = getBooleanSetting(props.settings.enable_neural_network_prediction, true);
        }
    }
};

const toggleKnnPrediction = (checked: boolean) => {
    form.enable_knn_prediction = checked;
};

const toggleLogisticRegressionPrediction = (checked: boolean) => {
    form.enable_logistic_regression_prediction = checked;
};

const toggleNeuralNetworkPrediction = (checked: boolean) => {
    form.enable_neural_network_prediction = checked;
};

const selectAlgorithm = (algorithm: 'neural_network' | 'logistic_regression' | 'knn') => {
    form.selected_ml_algorithm = algorithm;
};

const savingMLSettings = ref(false);

const saveMachineLearningSettings = async () => {
    savingMLSettings.value = true;
    console.log('Saving ML settings:', {
        selected_ml_algorithm: form.selected_ml_algorithm,
        enable_knn_prediction: form.enable_knn_prediction,
        enable_logistic_regression_prediction: form.enable_logistic_regression_prediction,
        enable_neural_network_prediction: form.enable_neural_network_prediction,
    });
    
    try {
        // Save selected algorithm and all three enable settings using bulk update
        const response = await axios.patch(
            '/admin/settings',
            {
                settings: [
                    {
                        key: 'selected_ml_algorithm',
                        value: form.selected_ml_algorithm,
                    },
                    {
                        key: 'enable_neural_network_prediction',
                        value: form.enable_neural_network_prediction,
                    },
                    {
                        key: 'enable_logistic_regression_prediction',
                        value: form.enable_logistic_regression_prediction,
                    },
                    {
                        key: 'enable_knn_prediction',
                        value: form.enable_knn_prediction,
                    },
                ],
            },
            {
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'application/json',
                    'Content-Type': 'application/json',
                },
            }
        );
        
        console.log('All ML settings saved successfully:', response.data);
        
        // Show success toast
        showSuccess(
            'Machine Learning Settings Saved',
            'Your machine learning algorithm preferences have been updated successfully.'
        );
        
        // Reload settings using Inertia to update props
        router.reload({ only: ['settings'] });
    } catch (error: any) {
        console.error('Error saving ML settings:', error);
        
        // Show error toast
        showError(
            'Failed to Save Settings',
            error.response?.data?.message || 'An error occurred while saving your settings. Please try again.'
        );
        
        // Revert to original values on error
        form.selected_ml_algorithm = props.settings.selected_ml_algorithm?.value ?? 'neural_network';
        form.enable_knn_prediction = getBooleanSetting(props.settings.enable_knn_prediction, true);
        form.enable_logistic_regression_prediction = getBooleanSetting(props.settings.enable_logistic_regression_prediction, true);
        form.enable_neural_network_prediction = getBooleanSetting(props.settings.enable_neural_network_prediction, true);
    } finally {
        savingMLSettings.value = false;
    }
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

                <!-- Machine Learning Settings -->
                <Card>
                    <CardHeader>
                        <CardTitle class="flex items-center gap-2">
                            <BrainCircuit class="h-5 w-5" />
                            Machine Learning Settings
                        </CardTitle>
                        <CardDescription>
                            Select which machine learning algorithm to use for disease diagnosis and medicine recommendations. Only one algorithm will be used at a time.
                        </CardDescription>
                    </CardHeader>
                    <CardContent class="space-y-6">
                        <!-- Algorithm Selection -->
                        <div class="space-y-4">
                            <Label class="text-base font-medium">
                                Selected Algorithm
                            </Label>
                            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                            <!-- Neural Network Card -->
                            <div
                                @click="selectAlgorithm('neural_network')"
                                :class="[
                                    'relative cursor-pointer rounded-lg border-2 p-5 transition-all duration-200',
                                    form.selected_ml_algorithm === 'neural_network'
                                        ? 'border-primary bg-primary/5 shadow-md hover:shadow-lg'
                                        : 'border-muted bg-muted/30 hover:border-muted-foreground/50 hover:bg-muted/50'
                                ]"
                            >
                                <div class="flex items-start justify-between mb-3">
                                    <div class="flex items-center gap-2">
                                        <div
                                            :class="[
                                                'h-3 w-3 rounded-full transition-colors',
                                                form.enable_neural_network_prediction
                                                    ? 'bg-primary'
                                                    : 'bg-muted-foreground/30'
                                            ]"
                                        />
                                        <h3 class="font-semibold text-base">Neural Network</h3>
                                    </div>
                                    <div
                                        v-if="form.selected_ml_algorithm === 'neural_network'"
                                        class="h-5 w-5 rounded-full bg-primary flex items-center justify-center"
                                    >
                                        <svg class="h-3 w-3 text-primary-foreground" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"></path>
                                        </svg>
                                    </div>
                                </div>
                                <p class="text-sm text-muted-foreground mb-2">
                                    Advanced deep learning algorithm for complex pattern recognition
                                </p>
                                <div class="mt-3 flex items-center gap-2 text-xs text-muted-foreground">
                                    <span class="font-medium">Accuracy:</span>
                                    <span>85-95%</span>
                                </div>
                            </div>

                            <!-- Logistic Regression Card -->
                            <div
                                @click="selectAlgorithm('logistic_regression')"
                                :class="[
                                    'relative cursor-pointer rounded-lg border-2 p-5 transition-all duration-200',
                                    form.selected_ml_algorithm === 'logistic_regression'
                                        ? 'border-primary bg-primary/5 shadow-md hover:shadow-lg'
                                        : 'border-muted bg-muted/30 hover:border-muted-foreground/50 hover:bg-muted/50'
                                ]"
                            >
                                <div class="flex items-start justify-between mb-3">
                                    <div class="flex items-center gap-2">
                                        <div
                                            :class="[
                                                'h-3 w-3 rounded-full transition-colors',
                                                form.enable_logistic_regression_prediction
                                                    ? 'bg-primary'
                                                    : 'bg-muted-foreground/30'
                                            ]"
                                        />
                                        <h3 class="font-semibold text-base">Logistic Regression</h3>
                                    </div>
                                    <div
                                        v-if="form.selected_ml_algorithm === 'logistic_regression'"
                                        class="h-5 w-5 rounded-full bg-primary flex items-center justify-center"
                                    >
                                        <svg class="h-3 w-3 text-primary-foreground" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"></path>
                                        </svg>
                                    </div>
                                </div>
                                <p class="text-sm text-muted-foreground mb-2">
                                    Statistical model for binary classification and pattern learning
                                </p>
                                <div class="mt-3 flex items-center gap-2 text-xs text-muted-foreground">
                                    <span class="font-medium">Accuracy:</span>
                                    <span>75-85%</span>
                                </div>
                            </div>

                            <!-- KNN Card -->
                            <div
                                @click="selectAlgorithm('knn')"
                                :class="[
                                    'relative cursor-pointer rounded-lg border-2 p-5 transition-all duration-200',
                                    form.selected_ml_algorithm === 'knn'
                                        ? 'border-primary bg-primary/5 shadow-md hover:shadow-lg'
                                        : 'border-muted bg-muted/30 hover:border-muted-foreground/50 hover:bg-muted/50'
                                ]"
                            >
                                <div class="flex items-start justify-between mb-3">
                                    <div class="flex items-center gap-2">
                                        <div
                                            :class="[
                                                'h-3 w-3 rounded-full transition-colors',
                                                form.enable_knn_prediction
                                                    ? 'bg-primary'
                                                    : 'bg-muted-foreground/30'
                                            ]"
                                        />
                                        <h3 class="font-semibold text-base">K-Nearest Neighbors</h3>
                                    </div>
                                    <div
                                        v-if="form.selected_ml_algorithm === 'knn'"
                                        class="h-5 w-5 rounded-full bg-primary flex items-center justify-center"
                                    >
                                        <svg class="h-3 w-3 text-primary-foreground" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"></path>
                                        </svg>
                                    </div>
                                </div>
                                <p class="text-sm text-muted-foreground mb-2">
                                    Fast similarity-based predictions using nearest neighbor matching
                                </p>
                                <div class="mt-3 flex items-center gap-2 text-xs text-muted-foreground">
                                    <span class="font-medium">Accuracy:</span>
                                    <span>65-80%</span>
                                </div>
                            </div>
                            </div>
                        </div>

                        <!-- Selected Algorithm Info -->
                        <div class="rounded-lg bg-blue-50 dark:bg-blue-900/20 p-4 border border-blue-200 dark:border-blue-800">
                            <p class="text-sm text-blue-800 dark:text-blue-200">
                                <strong>Current Selection:</strong>
                                <span v-if="form.selected_ml_algorithm === 'neural_network'">
                                    Neural Network - Advanced deep learning algorithm for complex pattern recognition (85-95% accuracy)
                                </span>
                                <span v-else-if="form.selected_ml_algorithm === 'logistic_regression'">
                                    Logistic Regression - Statistical model for binary classification (75-85% accuracy)
                                </span>
                                <span v-else-if="form.selected_ml_algorithm === 'knn'">
                                    K-Nearest Neighbors - Fast similarity-based predictions (65-80% accuracy)
                                </span>
                            </p>
                        </div>


                        <!-- Save Button -->
                        <div class="flex justify-end pt-4 border-t">
                            <Button 
                                @click="saveMachineLearningSettings"
                                :disabled="savingMLSettings"
                                size="lg"
                            >
                                <span v-if="savingMLSettings">Saving...</span>
                                <span v-else>Save Machine Learning Settings</span>
                            </Button>
                        </div>
                    </CardContent>
                </Card>

                <!-- Algorithm Performance Metrics -->
                <Card v-if="props.algorithmMetrics && availableAlgorithms.length > 0">
                    <CardHeader>
                        <CardTitle class="flex items-center gap-2">
                            <BarChart3 class="h-5 w-5" />
                            Algorithm Performance Metrics
                        </CardTitle>
                        <CardDescription>
                            View detailed performance metrics for each machine learning algorithm including classification report, confusion matrix, precision, recall, F1-score, and accuracy
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <Tabs v-model="activeAlgorithmTab" class="w-full">
                            <TabsList class="grid w-full" :style="{ gridTemplateColumns: `repeat(${availableAlgorithms.length}, 1fr)` }">
                                <TabsTrigger
                                    v-for="algorithm in availableAlgorithms"
                                    :key="algorithm.value"
                                    :value="algorithm.value"
                                >
                                    {{ algorithm.label }}
                                </TabsTrigger>
                            </TabsList>

                            <!-- Neural Network Tab -->
                            <TabsContent v-if="props.algorithmMetrics.neural_network" value="neural_network" class="mt-6">
                                <div class="space-y-6">
                                    <ClassificationReport 
                                        :confusion-matrix="props.algorithmMetrics.neural_network.confusion_matrix"
                                        algorithm="neural_network"
                                        :accuracy="props.algorithmMetrics.neural_network.accuracy"
                                        :total-samples="props.algorithmMetrics.neural_network.total_samples"
                                        class0-label="Not Eligible"
                                        class1-label="Eligible"
                                    />
                                    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                                        <div>
                                            <AlgorithmMetricsChart :metrics="props.algorithmMetrics.neural_network" />
                                        </div>
                                        <div>
                                            <ConfusionMatrixChart 
                                                :confusion-matrix="props.algorithmMetrics.neural_network.confusion_matrix"
                                                algorithm="neural_network"
                                            />
                                        </div>
                                    </div>
                                </div>
                            </TabsContent>

                            <!-- Logistic Regression Tab -->
                            <TabsContent v-if="props.algorithmMetrics.logistic_regression" value="logistic_regression" class="mt-6">
                                <div class="space-y-6">
                                    <ClassificationReport 
                                        :confusion-matrix="props.algorithmMetrics.logistic_regression.confusion_matrix"
                                        algorithm="logistic_regression"
                                        :accuracy="props.algorithmMetrics.logistic_regression.accuracy"
                                        :total-samples="props.algorithmMetrics.logistic_regression.total_samples"
                                        class0-label="Not Eligible"
                                        class1-label="Eligible"
                                    />
                                    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                                        <div>
                                            <AlgorithmMetricsChart :metrics="props.algorithmMetrics.logistic_regression" />
                                        </div>
                                        <div>
                                            <ConfusionMatrixChart 
                                                :confusion-matrix="props.algorithmMetrics.logistic_regression.confusion_matrix"
                                                algorithm="logistic_regression"
                                            />
                                        </div>
                                    </div>
                                </div>
                            </TabsContent>

                            <!-- KNN Tab -->
                            <TabsContent v-if="props.algorithmMetrics.knn" value="knn" class="mt-6">
                                <div class="space-y-6">
                                    <ClassificationReport 
                                        :confusion-matrix="props.algorithmMetrics.knn.confusion_matrix"
                                        algorithm="knn"
                                        :accuracy="props.algorithmMetrics.knn.accuracy"
                                        :total-samples="props.algorithmMetrics.knn.total_samples"
                                        class0-label="Not Eligible"
                                        class1-label="Eligible"
                                    />
                                    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                                        <div>
                                            <AlgorithmMetricsChart :metrics="props.algorithmMetrics.knn" />
                                        </div>
                                        <div>
                                            <ConfusionMatrixChart 
                                                :confusion-matrix="props.algorithmMetrics.knn.confusion_matrix"
                                                algorithm="knn"
                                            />
                                        </div>
                                    </div>
                                </div>
                            </TabsContent>

                        </Tabs>
                    </CardContent>
                </Card>

                <!-- No Metrics Available -->
                <Card v-else-if="!props.algorithmMetrics || availableAlgorithms.length === 0">
                    <CardContent class="text-center py-8 text-gray-500 dark:text-gray-400">
                        <p>No algorithm metrics available. Metrics are calculated based on training data.</p>
                    </CardContent>
                </Card>
            </div>
        </div>
    </AppLayout>
</template>
