import { ref, type Ref } from 'vue';
import { DiseaseMedicineModel } from '@/lib/ml/diseaseMedicineModel';
import { DiseaseSymptomModel } from '@/lib/ml/diseaseSymptomModel';
import { LogisticRegressionMedicineModel } from '@/lib/ml/logisticRegressionMedicine';
import { LogisticRegressionSymptomModel } from '@/lib/ml/logisticRegressionSymptom';
import { NeuralNetworkMedicineModel } from '@/lib/ml/neuralNetworkMedicine';
import { NeuralNetworkSymptomModel } from '@/lib/ml/neuralNetworkSymptom';

interface MedicineRecommendation {
    medicine_id: number;
    medicine_name: string;
    dosage: string;
    confidence: number;
}

interface DiseasePrediction {
    disease_id: number;
    disease_name: string;
    confidence: number;
    accuracy: string;
}

type AlgorithmType = 'knn' | 'logistic_regression' | 'neural_network';

/**
 * Composable for disease ML models
 */
export function useDiseaseML() {
    // KNN Models
    const medicineModel: Ref<DiseaseMedicineModel | null> = ref(null);
    const symptomModel: Ref<DiseaseSymptomModel | null> = ref(null);
    
    // Logistic Regression Models
    const lrMedicineModel: Ref<LogisticRegressionMedicineModel | null> = ref(null);
    const lrSymptomModel: Ref<LogisticRegressionSymptomModel | null> = ref(null);
    
    // Neural Network Models
    const nnMedicineModel: Ref<NeuralNetworkMedicineModel | null> = ref(null);
    const nnSymptomModel: Ref<NeuralNetworkSymptomModel | null> = ref(null);
    
    const isTraining = ref(false);
    const isTrained = ref(false);
    const error: Ref<string | null> = ref(null);
    const isKnnEnabled = ref(true);
    const isLogisticRegressionEnabled = ref(true);
    const isNeuralNetworkEnabled = ref(true);

    /**
     * Check if KNN prediction is enabled in settings
     */
    async function checkKnnEnabled(): Promise<boolean> {
        try {
            const response = await fetch('/admin/settings/api');
            const data = await response.json();
            isKnnEnabled.value = data.settings?.enable_knn_prediction ?? true;
            return isKnnEnabled.value;
        } catch (err) {
            // If settings can't be fetched, default to enabled
            console.warn('Could not fetch KNN settings, defaulting to enabled', err);
            isKnnEnabled.value = true;
            return true;
        }
    }

    /**
     * Check if Logistic Regression prediction is enabled in settings
     */
    async function checkLogisticRegressionEnabled(): Promise<boolean> {
        try {
            const response = await fetch('/admin/settings/api');
            const data = await response.json();
            isLogisticRegressionEnabled.value = data.settings?.enable_logistic_regression_prediction ?? true;
            return isLogisticRegressionEnabled.value;
        } catch (err) {
            // If settings can't be fetched, default to enabled
            console.warn('Could not fetch Logistic Regression settings, defaulting to enabled', err);
            isLogisticRegressionEnabled.value = true;
            return true;
        }
    }

    /**
     * Check if Neural Network prediction is enabled in settings
     */
    async function checkNeuralNetworkEnabled(): Promise<boolean> {
        try {
            const response = await fetch('/admin/settings/api');
            const data = await response.json();
            isNeuralNetworkEnabled.value = data.settings?.enable_neural_network_prediction ?? true;
            return isNeuralNetworkEnabled.value;
        } catch (err) {
            // If settings can't be fetched, default to enabled
            console.warn('Could not fetch Neural Network settings, defaulting to enabled', err);
            isNeuralNetworkEnabled.value = true;
            return true;
        }
    }

    /**
     * Get the selected ML algorithm from settings
     */
    async function getSelectedAlgorithm(): Promise<AlgorithmType> {
        try {
            const response = await fetch('/admin/settings/api');
            const data = await response.json();
            const selected = data.settings?.selected_ml_algorithm;
            
            // Validate the selected algorithm
            if (selected && ['neural_network', 'logistic_regression', 'knn'].includes(selected)) {
                return selected as AlgorithmType;
            }
            
            // Default to neural_network if not set or invalid
            return 'neural_network';
        } catch (err) {
            console.warn('Could not fetch selected algorithm, defaulting to neural_network', err);
            return 'neural_network';
        }
    }

    /**
     * Load and train the medicine recommendation model
     */
    async function trainMedicineModel(algorithm: AlgorithmType = 'knn'): Promise<void> {
        try {
            isTraining.value = true;
            error.value = null;

            const response = await fetch('/admin/diseases/training-data/medicines');
            const data = await response.json();
            const trainingData = data.data;

            if (algorithm === 'neural_network') {
                if (!nnMedicineModel.value) {
                    nnMedicineModel.value = new NeuralNetworkMedicineModel();
                }
                await nnMedicineModel.value.train(trainingData);
            } else if (algorithm === 'logistic_regression') {
                if (!lrMedicineModel.value) {
                    lrMedicineModel.value = new LogisticRegressionMedicineModel();
                }
                await lrMedicineModel.value.train(trainingData);
            } else {
                if (!medicineModel.value) {
                    medicineModel.value = new DiseaseMedicineModel();
                }
                await medicineModel.value.train(trainingData);
            }
            
            isTrained.value = true;
        } catch (err: any) {
            error.value = err.message || 'Failed to train medicine model';
            throw err;
        } finally {
            isTraining.value = false;
        }
    }

    /**
     * Load and train the symptom-based disease prediction model
     */
    async function trainSymptomModel(algorithm: AlgorithmType = 'knn'): Promise<void> {
        try {
            isTraining.value = true;
            error.value = null;

            const response = await fetch('/admin/diseases/training-data/symptoms');
            const data = await response.json();
            const trainingData = data.data;

            if (algorithm === 'neural_network') {
                if (!nnSymptomModel.value) {
                    nnSymptomModel.value = new NeuralNetworkSymptomModel();
                }
                await nnSymptomModel.value.train(trainingData);
            } else if (algorithm === 'logistic_regression') {
                if (!lrSymptomModel.value) {
                    lrSymptomModel.value = new LogisticRegressionSymptomModel();
                }
                await lrSymptomModel.value.train(trainingData);
            } else {
                if (!symptomModel.value) {
                    symptomModel.value = new DiseaseSymptomModel();
                }
                await symptomModel.value.train(trainingData);
            }
            
            isTrained.value = true;
        } catch (err: any) {
            error.value = err.message || 'Failed to train symptom model';
            throw err;
        } finally {
            isTraining.value = false;
        }
    }

    /**
     * Get medicine recommendations for a disease using ML
     * Uses only the selected algorithm (no fallback)
     */
    async function getMedicineRecommendations(
        diseaseId: number,
        topK: number = 3
    ): Promise<MedicineRecommendation[]> {
        // Get the selected algorithm
        const selectedAlgorithm = await getSelectedAlgorithm();
        
        try {
            if (selectedAlgorithm === 'neural_network') {
                if (!nnMedicineModel.value) {
                    await trainMedicineModel('neural_network');
                }

                if (nnMedicineModel.value) {
                    console.info('Using Neural Network for medicine recommendations');
                    return await nnMedicineModel.value.predictMedicines(diseaseId, topK);
                }
            } else if (selectedAlgorithm === 'logistic_regression') {
                if (!lrMedicineModel.value) {
                    await trainMedicineModel('logistic_regression');
                }

                if (lrMedicineModel.value) {
                    console.info('Using Logistic Regression for medicine recommendations');
                    return await lrMedicineModel.value.predictMedicines(diseaseId, topK);
                }
            } else if (selectedAlgorithm === 'knn') {
                if (!medicineModel.value) {
                    await trainMedicineModel('knn');
                }

                if (medicineModel.value) {
                    console.info('Using KNN for medicine recommendations');
                    return await medicineModel.value.predictMedicines(diseaseId, topK);
                }
            }
        } catch (err) {
            console.error(`Error using ${selectedAlgorithm} for medicine recommendations:`, err);
            return [];
        }

        console.warn(`Selected algorithm ${selectedAlgorithm} is not available`);
        return [];
    }

    /**
     * Predict diseases based on symptoms using ML
     * Uses only the selected algorithm (no fallback)
     */
    async function predictDiseasesFromSymptoms(
        symptomIds: number[],
        topK: number = 10
    ): Promise<DiseasePrediction[]> {
        // Get the selected algorithm
        const selectedAlgorithm = await getSelectedAlgorithm();
        
        try {
            if (selectedAlgorithm === 'neural_network') {
                if (!nnSymptomModel.value) {
                    await trainSymptomModel('neural_network');
                }

                if (nnSymptomModel.value) {
                    console.info('Using Neural Network for disease prediction');
                    return await nnSymptomModel.value.predictDiseases(symptomIds, topK);
                }
            } else if (selectedAlgorithm === 'logistic_regression') {
                if (!lrSymptomModel.value) {
                    await trainSymptomModel('logistic_regression');
                }

                if (lrSymptomModel.value) {
                    console.info('Using Logistic Regression for disease prediction');
                    return await lrSymptomModel.value.predictDiseases(symptomIds, topK);
                }
            } else if (selectedAlgorithm === 'knn') {
                if (!symptomModel.value) {
                    await trainSymptomModel('knn');
                }

                if (symptomModel.value) {
                    console.info('Using KNN for disease prediction');
                    return await symptomModel.value.predictDiseases(symptomIds, topK);
                }
            }
        } catch (err) {
            console.error(`Error using ${selectedAlgorithm} for disease prediction:`, err);
            return [];
        }

        console.warn(`Selected algorithm ${selectedAlgorithm} is not available`);
        return [];
    }

    /**
     * Clean up models
     */
    function dispose(): void {
        if (medicineModel.value) {
            medicineModel.value.dispose();
            medicineModel.value = null;
        }
        if (symptomModel.value) {
            symptomModel.value.dispose();
            symptomModel.value = null;
        }
        if (lrMedicineModel.value) {
            lrMedicineModel.value.dispose();
            lrMedicineModel.value = null;
        }
        if (lrSymptomModel.value) {
            lrSymptomModel.value.dispose();
            lrSymptomModel.value = null;
        }
        if (nnMedicineModel.value) {
            nnMedicineModel.value.dispose();
            nnMedicineModel.value = null;
        }
        if (nnSymptomModel.value) {
            nnSymptomModel.value.dispose();
            nnSymptomModel.value = null;
        }
        isTrained.value = false;
    }

    return {
        isTraining,
        isTrained,
        error,
        isKnnEnabled,
        isLogisticRegressionEnabled,
        isNeuralNetworkEnabled,
        checkKnnEnabled,
        checkLogisticRegressionEnabled,
        checkNeuralNetworkEnabled,
        getSelectedAlgorithm,
        trainMedicineModel,
        trainSymptomModel,
        getMedicineRecommendations,
        predictDiseasesFromSymptoms,
        dispose,
    };
}














