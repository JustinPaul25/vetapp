import { ref, type Ref } from 'vue';
import { DiseaseMedicineModel } from '@/lib/ml/diseaseMedicineModel';
import { DiseaseSymptomModel } from '@/lib/ml/diseaseSymptomModel';

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

/**
 * Composable for disease ML models
 */
export function useDiseaseML() {
    const medicineModel: Ref<DiseaseMedicineModel | null> = ref(null);
    const symptomModel: Ref<DiseaseSymptomModel | null> = ref(null);
    const isTraining = ref(false);
    const isTrained = ref(false);
    const error: Ref<string | null> = ref(null);

    /**
     * Load and train the medicine recommendation model
     */
    async function trainMedicineModel(): Promise<void> {
        try {
            isTraining.value = true;
            error.value = null;

            const response = await fetch('/admin/diseases/training-data/medicines');
            const data = await response.json();
            const trainingData = data.data;

            if (!medicineModel.value) {
                medicineModel.value = new DiseaseMedicineModel();
            }

            await medicineModel.value.train(trainingData);
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
    async function trainSymptomModel(): Promise<void> {
        try {
            isTraining.value = true;
            error.value = null;

            const response = await fetch('/admin/diseases/training-data/symptoms');
            const data = await response.json();
            const trainingData = data.data;

            if (!symptomModel.value) {
                symptomModel.value = new DiseaseSymptomModel();
            }

            await symptomModel.value.train(trainingData);
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
     */
    async function getMedicineRecommendations(
        diseaseId: number,
        topK: number = 3
    ): Promise<MedicineRecommendation[]> {
        if (!medicineModel.value) {
            await trainMedicineModel();
        }

        if (!medicineModel.value) {
            throw new Error('Medicine model is not available');
        }

        return await medicineModel.value.predictMedicines(diseaseId, topK);
    }

    /**
     * Predict diseases based on symptoms using ML
     */
    async function predictDiseasesFromSymptoms(
        symptomIds: number[],
        topK: number = 10
    ): Promise<DiseasePrediction[]> {
        if (!symptomModel.value) {
            await trainSymptomModel();
        }

        if (!symptomModel.value) {
            throw new Error('Symptom model is not available');
        }

        return await symptomModel.value.predictDiseases(symptomIds, topK);
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
        isTrained.value = false;
    }

    return {
        isTraining,
        isTrained,
        error,
        trainMedicineModel,
        trainSymptomModel,
        getMedicineRecommendations,
        predictDiseasesFromSymptoms,
        dispose,
    };
}




