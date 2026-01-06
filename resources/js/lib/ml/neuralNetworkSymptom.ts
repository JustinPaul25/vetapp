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
 * Deep Neural Network Model for Disease-Symptom Predictions
 * Uses sophisticated multi-layer architecture with advanced regularization
 * More complex than Logistic Regression for better pattern recognition
 */
export class NeuralNetworkSymptomModel {
    private model: tf.Sequential | null = null;
    private diseaseIndexMap: Map<number, number> = new Map();
    private symptomIndexMap: Map<number, number> = new Map();
    private allDiseases: Map<number, string> = new Map();
    private isTrained = false;
    private numSymptoms = 0;
    private numDiseases = 0;

    /**
     * Train the deep neural network model with disease-symptom relationships
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

        this.numDiseases = diseaseArray.length;
        this.numSymptoms = symptomArray.length;

        // Prepare training data
        const trainingExamples: number[][] = [];
        const trainingLabels: number[][] = [];

        trainingData.forEach((diseaseData) => {
            const diseaseIndex = this.diseaseIndexMap.get(diseaseData.disease_id)!;

            // Create symptom vector (binary: symptom present = 1, absent = 0)
            const symptomVec = new Array(this.numSymptoms).fill(0);
            diseaseData.symptoms.forEach((symptom) => {
                const symptomIndex = this.symptomIndexMap.get(symptom.symptom_id);
                if (symptomIndex !== undefined) {
                    symptomVec[symptomIndex] = 1;
                }
            });

            // Create one-hot encoded label for disease
            const diseaseLabel = new Array(this.numDiseases).fill(0);
            diseaseLabel[diseaseIndex] = 1;

            trainingExamples.push(symptomVec);
            trainingLabels.push(diseaseLabel);
        });

        // Build deep neural network with sophisticated architecture
        this.model = tf.sequential({
            layers: [
                // Input layer with larger capacity
                tf.layers.dense({
                    inputShape: [this.numSymptoms],
                    units: Math.max(256, this.numSymptoms * 3), // Larger first layer
                    activation: 'relu',
                    kernelInitializer: 'heNormal',
                    kernelRegularizer: tf.regularizers.l2({ l2: 0.001 }),
                }),
                tf.layers.batchNormalization(),
                tf.layers.dropout({ rate: 0.4 }), // Higher dropout for symptom data
                
                // Second hidden layer
                tf.layers.dense({
                    units: 128,
                    activation: 'relu',
                    kernelInitializer: 'heNormal',
                    kernelRegularizer: tf.regularizers.l2({ l2: 0.001 }),
                }),
                tf.layers.batchNormalization(),
                tf.layers.dropout({ rate: 0.35 }),
                
                // Third hidden layer
                tf.layers.dense({
                    units: 64,
                    activation: 'relu',
                    kernelInitializer: 'heNormal',
                    kernelRegularizer: tf.regularizers.l2({ l2: 0.001 }),
                }),
                tf.layers.batchNormalization(),
                tf.layers.dropout({ rate: 0.3 }),
                
                // Fourth hidden layer
                tf.layers.dense({
                    units: 32,
                    activation: 'relu',
                    kernelInitializer: 'heNormal',
                }),
                tf.layers.dropout({ rate: 0.2 }),
                
                // Output layer with softmax for multi-class
                tf.layers.dense({
                    units: this.numDiseases,
                    activation: 'softmax',
                    kernelInitializer: 'glorotNormal',
                }),
            ],
        });

        // Compile model with advanced settings
        this.model.compile({
            optimizer: tf.train.adam(0.0005), // Lower learning rate
            loss: 'categoricalCrossentropy',
            metrics: ['accuracy', 'categoricalAccuracy'],
        });

        // Convert to tensors
        const xs = tf.tensor2d(trainingExamples);
        const ys = tf.tensor2d(trainingLabels);

        // Train the model with early stopping
        let bestLoss = Infinity;
        let patience = 0;
        const maxPatience = 15;

        await this.model.fit(xs, ys, {
            epochs: 250, // More epochs for complex patterns
            batchSize: 16,
            validationSplit: 0.2,
            shuffle: true,
            verbose: 0,
            callbacks: {
                onEpochEnd: (epoch, logs) => {
                    if (epoch % 30 === 0) {
                        console.log(
                            `NN Symptom - Epoch ${epoch}: ` +
                            `loss=${logs?.loss.toFixed(4)}, ` +
                            `acc=${logs?.acc.toFixed(4)}, ` +
                            `val_loss=${logs?.val_loss?.toFixed(4)}`
                        );
                    }

                    // Early stopping
                    if (logs?.val_loss !== undefined) {
                        if (logs.val_loss < bestLoss) {
                            bestLoss = logs.val_loss;
                            patience = 0;
                        } else {
                            patience++;
                            if (patience >= maxPatience) {
                                console.log(`Early stopping at epoch ${epoch}`);
                                if (this.model) {
                                    (this.model as any).stopTraining = true;
                                }
                            }
                        }
                    }
                },
            },
        });

        // Clean up
        xs.dispose();
        ys.dispose();

        this.isTrained = true;
        console.log('Neural Network Symptom Model trained successfully');
    }

    /**
     * Predict diseases based on symptoms using deep neural network
     */
    async predictDiseases(symptomIds: number[], topK: number = 10): Promise<DiseasePrediction[]> {
        if (!this.isTrained || !this.model) {
            throw new Error('Model is not trained. Call train() first.');
        }

        if (symptomIds.length === 0) {
            return [];
        }

        // Create symptom vector
        const symptomVector = new Array(this.numSymptoms).fill(0);
        symptomIds.forEach((symptomId) => {
            const symptomIndex = this.symptomIndexMap.get(symptomId);
            if (symptomIndex !== undefined) {
                symptomVector[symptomIndex] = 1;
            }
        });

        // Predict
        const inputTensor = tf.tensor2d([symptomVector]);
        const prediction = this.model.predict(inputTensor) as tf.Tensor;
        const probabilities = await prediction.data();

        // Clean up
        inputTensor.dispose();
        prediction.dispose();

        // Get disease IDs in order
        const diseaseArray = Array.from(this.diseaseIndexMap.keys());

        // Create predictions array
        const predictions: Array<{ diseaseId: number; score: number }> = [];

        probabilities.forEach((prob, index) => {
            const diseaseId = diseaseArray[index];
            if (diseaseId !== undefined && prob > 0.02) { // Slightly higher threshold for NN
                predictions.push({
                    diseaseId,
                    score: prob,
                });
            }
        });

        // Sort by probability and get top K
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
        if (this.model) {
            this.model.dispose();
            this.model = null;
        }
        this.isTrained = false;
    }
}


