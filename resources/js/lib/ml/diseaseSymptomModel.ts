import * as tf from '@tensorflow/tfjs';

interface TrainingData {
    disease_id: number;
    disease_name: string;
    symptoms: Array<{
        symptom_id: number;
        symptom_name: string;
    }>;
}

interface DiseasePrediction {
    disease_id: number;
    disease_name: string;
    confidence: number;
    accuracy: string;
}

/**
 * Disease-Symptom ML Model using TensorFlow.js
 * Uses Jaccard similarity (intersection over union) for disease prediction
 */
export class DiseaseSymptomModel {
    private diseaseIndexMap: Map<number, number> = new Map();
    private symptomIndexMap: Map<number, number> = new Map();
    private diseaseVectors: tf.Tensor2D | null = null;
    private allDiseases: Map<number, string> = new Map();
    private isTrained = false;

    /**
     * Train the model with disease-symptom relationships
     */
    async train(trainingData: TrainingData[]): Promise<void> {
        if (trainingData.length === 0) {
            throw new Error('Training data is empty');
        }

        // Build index maps
        const uniqueDiseases = new Set<number>();
        const uniqueSymptoms = new Set<number>();

        trainingData.forEach((item) => {
            uniqueDiseases.add(item.disease_id);
            this.allDiseases.set(item.disease_id, item.disease_name);
            item.symptoms.forEach((symptom) => {
                uniqueSymptoms.add(symptom.symptom_id);
            });
        });

        // Create index mappings
        const diseaseArray = Array.from(uniqueDiseases);
        const symptomArray = Array.from(uniqueSymptoms);

        diseaseArray.forEach((id, index) => {
            this.diseaseIndexMap.set(id, index);
        });

        symptomArray.forEach((id, index) => {
            this.symptomIndexMap.set(id, index);
        });

        // Create one-hot encoded vectors for diseases
        const numDiseases = diseaseArray.length;
        const numSymptoms = symptomArray.length;

        const diseaseVectorData: number[][] = [];

        diseaseArray.forEach((diseaseId) => {
            const diseaseData = trainingData.find((d) => d.disease_id === diseaseId);
            const diseaseVec = new Array(numSymptoms).fill(0);

            if (diseaseData) {
                diseaseData.symptoms.forEach((symptom) => {
                    const symptomIndex = this.symptomIndexMap.get(symptom.symptom_id);
                    if (symptomIndex !== undefined) {
                        diseaseVec[symptomIndex] = 1;
                    }
                });
            }

            diseaseVectorData.push(diseaseVec);
        });

        // Convert to tensor
        this.diseaseVectors = tf.tensor2d(diseaseVectorData);
        this.isTrained = true;
    }

    /**
     * Predict diseases based on symptoms using Jaccard similarity
     */
    async predictDiseases(symptomIds: number[], topK: number = 10): Promise<DiseasePrediction[]> {
        if (!this.isTrained || !this.diseaseVectors) {
            throw new Error('Model is not trained. Call train() first.');
        }

        if (symptomIds.length === 0) {
            return [];
        }

        // Create symptom vector from input
        const numSymptoms = this.symptomIndexMap.size;
        const symptomVector = new Array(numSymptoms).fill(0);

        symptomIds.forEach((symptomId) => {
            const symptomIndex = this.symptomIndexMap.get(symptomId);
            if (symptomIndex !== undefined) {
                symptomVector[symptomIndex] = 1;
            }
        });

        const inputVector = tf.tensor1d(symptomVector).expandDims(0);

        // Calculate Jaccard similarity (intersection over union) for each disease
        const predictions: Array<{ diseaseId: number; score: number }> = [];

        // For each disease, calculate Jaccard similarity
        for (let i = 0; i < this.diseaseVectors.shape[0]; i++) {
            const diseaseVector = this.diseaseVectors.slice([i, 0], [1, -1]);

            // Calculate intersection (element-wise min)
            const intersection = tf.minimum(inputVector, diseaseVector);
            const intersectionSum = tf.sum(intersection);

            // Calculate union (element-wise max)
            const union = tf.maximum(inputVector, diseaseVector);
            const unionSum = tf.sum(union);

            // Jaccard = intersection / union
            const jaccard = intersectionSum.div(unionSum);

            const jaccardValue = (await jaccard.data())[0];

            const diseaseId = Array.from(this.diseaseIndexMap.keys())[i];
            if (diseaseId !== undefined && jaccardValue > 0) {
                predictions.push({
                    diseaseId,
                    score: jaccardValue,
                });
            }

            // Clean up
            diseaseVector.dispose();
            intersection.dispose();
            intersectionSum.dispose();
            union.dispose();
            unionSum.dispose();
            jaccard.dispose();
        }

        // Clean up input vector
        inputVector.dispose();

        // Sort by score and get top K
        predictions.sort((a, b) => b.score - a.score);

        return predictions.slice(0, topK).map((pred) => {
            const diseaseName = this.allDiseases.get(pred.diseaseId) || 'Unknown';
            const accuracy = (pred.score * 100).toFixed(2);
            return {
                disease_id: pred.diseaseId,
                disease_name: diseaseName,
                confidence: Math.round(pred.score * 100) / 100,
                accuracy: `${accuracy}%`,
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
        this.isTrained = false;
    }
}













