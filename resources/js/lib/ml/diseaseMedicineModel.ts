import * as tf from '@tensorflow/tfjs';

interface TrainingData {
    disease_id: number;
    disease_name: string;
    medicines: Array<{
        medicine_id: number;
        medicine_name: string;
        dosage: string;
    }>;
}

interface MedicineRecommendation {
    medicine_id: number;
    medicine_name: string;
    dosage: string;
    confidence: number;
}

/**
 * Disease-Medicine ML Model using TensorFlow.js
 * Uses KNN-like approach with cosine similarity for medicine recommendations
 */
export class DiseaseMedicineModel {
    private model: tf.Sequential | null = null;
    private diseaseIndexMap: Map<number, number> = new Map();
    private medicineIndexMap: Map<number, number> = new Map();
    private diseaseVectors: tf.Tensor2D | null = null;
    private medicineVectors: tf.Tensor2D | null = null;
    private allMedicines: Map<number, { name: string; dosage: string }> = new Map();
    private isTrained = false;

    /**
     * Train the model with disease-medicine relationships
     */
    async train(trainingData: TrainingData[]): Promise<void> {
        if (trainingData.length === 0) {
            throw new Error('Training data is empty');
        }

        // Build index maps
        const uniqueDiseases = new Set<number>();
        const uniqueMedicines = new Set<number>();

        trainingData.forEach((item) => {
            uniqueDiseases.add(item.disease_id);
            item.medicines.forEach((med) => {
                uniqueMedicines.add(med.medicine_id);
                this.allMedicines.set(med.medicine_id, {
                    name: med.medicine_name,
                    dosage: med.dosage,
                });
            });
        });

        // Create index mappings
        const diseaseArray = Array.from(uniqueDiseases);
        const medicineArray = Array.from(uniqueMedicines);

        diseaseArray.forEach((id, index) => {
            this.diseaseIndexMap.set(id, index);
        });

        medicineArray.forEach((id, index) => {
            this.medicineIndexMap.set(id, index);
        });

        // Create one-hot encoded vectors
        const numDiseases = diseaseArray.length;
        const numMedicines = medicineArray.length;

        // Build disease vectors (one-hot encoding based on associated medicines)
        const diseaseVectorData: number[][] = [];
        const medicineVectorData: number[][] = [];

        diseaseArray.forEach((diseaseId) => {
            const diseaseData = trainingData.find((d) => d.disease_id === diseaseId);
            const diseaseVec = new Array(numMedicines).fill(0);

            if (diseaseData) {
                diseaseData.medicines.forEach((med) => {
                    const medIndex = this.medicineIndexMap.get(med.medicine_id);
                    if (medIndex !== undefined) {
                        diseaseVec[medIndex] = 1;
                    }
                });
            }

            diseaseVectorData.push(diseaseVec);
        });

        // Build medicine vectors (one-hot encoding based on associated diseases)
        medicineArray.forEach((medicineId) => {
            const medicineVec = new Array(numDiseases).fill(0);

            trainingData.forEach((diseaseData) => {
                const hasMedicine = diseaseData.medicines.some((med) => med.medicine_id === medicineId);
                if (hasMedicine) {
                    const diseaseIndex = this.diseaseIndexMap.get(diseaseData.disease_id);
                    if (diseaseIndex !== undefined) {
                        medicineVec[diseaseIndex] = 1;
                    }
                }
            });

            medicineVectorData.push(medicineVec);
        });

        // Convert to tensors
        this.diseaseVectors = tf.tensor2d(diseaseVectorData);
        this.medicineVectors = tf.tensor2d(medicineVectorData);

        this.isTrained = true;
    }

    /**
     * Predict medicines for a given disease using cosine similarity (KNN-like)
     */
    async predictMedicines(diseaseId: number, topK: number = 3): Promise<MedicineRecommendation[]> {
        if (!this.isTrained || !this.diseaseVectors || !this.medicineVectors) {
            throw new Error('Model is not trained. Call train() first.');
        }

        const diseaseIndex = this.diseaseIndexMap.get(diseaseId);
        if (diseaseIndex === undefined) {
            return [];
        }

        // Get disease vector
        const diseaseVector = this.diseaseVectors.slice([diseaseIndex, 0], [1, -1]);

        // Calculate cosine similarity with all medicines
        const medicineNorm = tf.norm(this.medicineVectors, 2, 1, true);
        const diseaseNorm = tf.norm(diseaseVector, 2, 1, true);

        const dotProduct = tf.matMul(diseaseVector, this.medicineVectors.transpose());
        const similarity = dotProduct.div(tf.matMul(diseaseNorm, medicineNorm.transpose()));

        // Get top K medicines
        const similarityData = await similarity.data();
        const medicineIndices = Array.from(this.medicineIndexMap.keys());

        const recommendations: Array<{ medicineId: number; confidence: number }> = [];

        similarityData.forEach((score, index) => {
            const medicineId = medicineIndices[index];
            if (medicineId !== undefined && score > 0) {
                recommendations.push({
                    medicineId,
                    confidence: score,
                });
            }
        });

        // Sort by confidence and get top K
        recommendations.sort((a, b) => b.confidence - a.confidence);

        // Clean up tensors
        diseaseVector.dispose();
        medicineNorm.dispose();
        diseaseNorm.dispose();
        dotProduct.dispose();
        similarity.dispose();

        return recommendations.slice(0, topK).map((rec) => {
            const medicine = this.allMedicines.get(rec.medicineId);
            return {
                medicine_id: rec.medicineId,
                medicine_name: medicine?.name || 'Unknown',
                dosage: medicine?.dosage || '',
                confidence: Math.round(rec.confidence * 100) / 100,
            };
        });
    }

    /**
     * Dispose of model resources
     */
    dispose(): void {
        if (this.diseaseVectors) {
            this.diseaseVectors.dispose();
            this.diseaseVectors = null;
        }
        if (this.medicineVectors) {
            this.medicineVectors.dispose();
            this.medicineVectors = null;
        }
        if (this.model) {
            this.model.dispose();
            this.model = null;
        }
        this.isTrained = false;
    }
}












