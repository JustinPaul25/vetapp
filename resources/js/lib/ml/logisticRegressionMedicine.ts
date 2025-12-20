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
 * Logistic Regression Model for Disease-Medicine Recommendations
 * Uses binary classification with sigmoid activation to predict medicine relevance
 */
export class LogisticRegressionMedicineModel {
    private model: tf.Sequential | null = null;
    private diseaseIndexMap: Map<number, number> = new Map();
    private medicineIndexMap: Map<number, number> = new Map();
    private allMedicines: Map<number, { name: string; dosage: string }> = new Map();
    private isTrained = false;
    private numDiseases = 0;
    private numMedicines = 0;

    /**
     * Train the logistic regression model with disease-medicine relationships
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

        this.numDiseases = diseaseArray.length;
        this.numMedicines = medicineArray.length;

        // Prepare training data: for each disease-medicine pair, create a binary label
        const trainingExamples: number[][] = [];
        const trainingLabels: number[][] = [];

        diseaseArray.forEach((diseaseId) => {
            const diseaseData = trainingData.find((d) => d.disease_id === diseaseId);
            const diseaseIndex = this.diseaseIndexMap.get(diseaseId)!;

            medicineArray.forEach((medicineId) => {
                // One-hot encode disease
                const diseaseVec = new Array(this.numDiseases).fill(0);
                diseaseVec[diseaseIndex] = 1;

                // Check if this medicine is prescribed for this disease
                const hasMedicine = diseaseData?.medicines.some((med) => med.medicine_id === medicineId) ?? false;

                trainingExamples.push(diseaseVec);
                trainingLabels.push([hasMedicine ? 1 : 0]);
            });
        });

        // Build logistic regression model
        this.model = tf.sequential({
            layers: [
                tf.layers.dense({
                    inputShape: [this.numDiseases],
                    units: Math.max(32, Math.floor(this.numDiseases / 2)), // Hidden layer
                    activation: 'relu',
                    kernelInitializer: 'heNormal',
                }),
                tf.layers.dropout({ rate: 0.2 }), // Prevent overfitting
                tf.layers.dense({
                    units: 16,
                    activation: 'relu',
                    kernelInitializer: 'heNormal',
                }),
                tf.layers.dense({
                    units: 1,
                    activation: 'sigmoid', // Logistic regression activation
                    kernelInitializer: 'glorotNormal',
                }),
            ],
        });

        // Compile model with binary cross-entropy loss
        this.model.compile({
            optimizer: tf.train.adam(0.001),
            loss: 'binaryCrossentropy',
            metrics: ['accuracy'],
        });

        // Convert to tensors
        const xs = tf.tensor2d(trainingExamples);
        const ys = tf.tensor2d(trainingLabels);

        // Train the model
        await this.model.fit(xs, ys, {
            epochs: 100,
            batchSize: 32,
            validationSplit: 0.2,
            verbose: 0,
            callbacks: {
                onEpochEnd: (epoch, logs) => {
                    if (epoch % 20 === 0) {
                        console.log(`Epoch ${epoch}: loss = ${logs?.loss.toFixed(4)}, accuracy = ${logs?.acc.toFixed(4)}`);
                    }
                },
            },
        });

        // Clean up
        xs.dispose();
        ys.dispose();

        this.isTrained = true;
        console.log('Logistic Regression Medicine Model trained successfully');
    }

    /**
     * Predict medicines for a given disease using logistic regression
     */
    async predictMedicines(diseaseId: number, topK: number = 3): Promise<MedicineRecommendation[]> {
        if (!this.isTrained || !this.model) {
            throw new Error('Model is not trained. Call train() first.');
        }

        const diseaseIndex = this.diseaseIndexMap.get(diseaseId);
        if (diseaseIndex === undefined) {
            return [];
        }

        // Create one-hot encoded disease vector
        const diseaseVec = new Array(this.numDiseases).fill(0);
        diseaseVec[diseaseIndex] = 1;

        // Get predictions for all medicines
        const medicineArray = Array.from(this.medicineIndexMap.keys());
        const predictions: Array<{ medicineId: number; confidence: number }> = [];

        // Predict for each medicine
        for (const medicineId of medicineArray) {
            const inputTensor = tf.tensor2d([diseaseVec]);
            const prediction = this.model.predict(inputTensor) as tf.Tensor;
            const confidence = (await prediction.data())[0];

            if (confidence > 0.1) { // Threshold for relevance
                predictions.push({
                    medicineId,
                    confidence,
                });
            }

            inputTensor.dispose();
            prediction.dispose();
        }

        // Sort by confidence and get top K
        predictions.sort((a, b) => b.confidence - a.confidence);

        return predictions.slice(0, topK).map((rec) => {
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
        if (this.model) {
            this.model.dispose();
            this.model = null;
        }
        this.isTrained = false;
    }
}

