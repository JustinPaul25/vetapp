<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { Head } from '@inertiajs/vue3';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { MapPin, AlertCircle } from 'lucide-vue-next';
import { onMounted, ref } from 'vue';
import L from 'leaflet';
import 'leaflet/dist/leaflet.css';
import { dashboard } from '@/routes';

interface OutbreakZone {
    address: string;
    lat: number;
    lng: number;
    count: number;
}

interface DiseaseCase {
    disease_id: number;
    disease_name: string;
    lat: number;
    lng: number;
    address: string;
    appointment_date: string | null;
}

interface TopDisease {
    name: string;
    count: number;
}

interface Props {
    outbreakZones: OutbreakZone[];
    cases: DiseaseCase[];
    topDiseases: TopDisease[];
    diseaseColors: Record<string, string>;
}

const props = defineProps<Props>();

const breadcrumbs = [
    { title: 'Dashboard', href: dashboard().url },
    { title: 'Disease Map', href: '#' },
];

const mapContainer = ref<HTMLElement | null>(null);
const map = ref<L.Map | null>(null);

// Fix for default marker icons in Leaflet with Vite
delete (L.Icon.Default.prototype as any)._getIconUrl;
L.Icon.Default.mergeOptions({
    iconRetinaUrl: 'https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/images/marker-icon-2x.png',
    iconUrl: 'https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/images/marker-icon.png',
    shadowUrl: 'https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/images/marker-shadow.png',
});

onMounted(() => {
    if (!mapContainer.value) return;

    // Initialize map centered on Panabo City
    map.value = L.map(mapContainer.value).setView([7.3075, 125.6830], 13);

    // Add OpenStreetMap tile layer
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '© OpenStreetMap contributors',
        maxZoom: 19,
    }).addTo(map.value);

    // Add hotspot zones (circles)
    props.outbreakZones.forEach((zone) => {
        // Add circle for hotspot zone
        L.circle([zone.lat, zone.lng], {
            color: '#dc2626',
            fillColor: '#dc2626',
            fillOpacity: 0.2,
            radius: 1000, // 1000 meters
            weight: 2,
        })
            .addTo(map.value!)
            .bindPopup(
                `<strong>Hotspot</strong><br/>${zone.address}<br/>Cases: ${zone.count}`
            );

        // Add marker for zone center
        L.marker([zone.lat, zone.lng], {
            icon: L.icon({
                iconUrl: 'https://raw.githubusercontent.com/pointhi/leaflet-color-markers/master/img/marker-icon-red.png',
                shadowUrl: 'https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/images/marker-shadow.png',
                iconSize: [25, 41],
                iconAnchor: [12, 41],
                popupAnchor: [1, -34],
                shadowSize: [41, 41],
            }),
        })
            .addTo(map.value!)
            .bindPopup(
                `<strong>Hotspot</strong><br/>${zone.address}<br/>Cases: ${zone.count}`
            );
    });

    // Add disease case markers
    props.cases.forEach((caseItem) => {
        const color = props.diseaseColors[caseItem.disease_name] || '#3388ff';
        
        // Create custom colored marker
        const customIcon = L.divIcon({
            className: 'custom-marker',
            html: `<div style="background-color: ${color}; width: 12px; height: 12px; border-radius: 50%; border: 2px solid white; box-shadow: 0 2px 4px rgba(0,0,0,0.3);"></div>`,
            iconSize: [12, 12],
            iconAnchor: [6, 6],
        });

        const popupContent = `
            <div>
                <strong>${caseItem.disease_name}</strong><br/>
                Address: ${caseItem.address}<br/>
                ${caseItem.appointment_date ? `Date: ${new Date(caseItem.appointment_date).toLocaleDateString()}` : ''}
            </div>
        `;

        L.marker([caseItem.lat, caseItem.lng], { icon: customIcon })
            .addTo(map.value!)
            .bindPopup(popupContent);
    });
});
</script>

<template>
    <Head title="Disease Map" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="container mx-auto p-6">
            <Card>
                <CardHeader>
                    <div class="flex items-center gap-2">
                        <MapPin class="h-5 w-5" />
                        <div>
                            <CardTitle>Disease Map</CardTitle>
                            <CardDescription>
                                Geographic visualization of disease cases
                            </CardDescription>
                        </div>
                    </div>
                </CardHeader>
                <CardContent>
                    <div class="grid grid-cols-1 lg:grid-cols-4 gap-6">
                        <!-- Map -->
                        <div class="lg:col-span-3">
                            <div
                                ref="mapContainer"
                                class="w-full h-[600px] rounded-lg border"
                                style="z-index: 1"
                            ></div>
                        </div>

                        <!-- Legend and Stats -->
                        <div class="space-y-4">
                            <!-- Top Diseases Legend -->
                            <Card>
                                <CardHeader class="pb-3">
                                    <CardTitle class="text-sm flex items-center gap-2">
                                        <AlertCircle class="h-4 w-4" />
                                        Top Diseases
                                    </CardTitle>
                                </CardHeader>
                                <CardContent class="space-y-2">
                                    <div
                                        v-for="disease in topDiseases"
                                        :key="disease.name"
                                        class="flex items-center gap-2 text-sm"
                                    >
                                        <div
                                            class="w-4 h-4 rounded-full border-2 border-white shadow-sm"
                                            :style="{
                                                backgroundColor: diseaseColors[disease.name] || '#3388ff',
                                            }"
                                        ></div>
                                        <span class="flex-1 truncate">{{ disease.name }}</span>
                                        <span class="text-muted-foreground font-semibold">
                                            {{ disease.count }}
                                        </span>
                                    </div>
                                    <div v-if="topDiseases.length === 0" class="text-sm text-muted-foreground">
                                        No disease data available
                                    </div>
                                </CardContent>
                            </Card>

                            <!-- Hotspots -->
                            <Card>
                                <CardHeader class="pb-3">
                                    <CardTitle class="text-sm">Hotspots</CardTitle>
                                </CardHeader>
                                <CardContent class="space-y-2">
                                    <div
                                        v-for="(zone, index) in outbreakZones"
                                        :key="index"
                                        class="text-sm"
                                    >
                                        <div class="font-medium truncate">{{ zone.address }}</div>
                                        <div class="text-muted-foreground">
                                            {{ zone.count }} case{{ zone.count !== 1 ? 's' : '' }}
                                        </div>
                                    </div>
                                    <div v-if="outbreakZones.length === 0" class="text-sm text-muted-foreground">
                                        No hotspots detected
                                    </div>
                                </CardContent>
                            </Card>

                            <!-- Stats -->
                            <Card>
                                <CardHeader class="pb-3">
                                    <CardTitle class="text-sm">Statistics</CardTitle>
                                </CardHeader>
                                <CardContent class="space-y-2 text-sm">
                                    <div class="flex justify-between">
                                        <span class="text-muted-foreground">Total Cases:</span>
                                        <span class="font-semibold">{{ cases.length }}</span>
                                    </div>
                                    <div class="flex justify-between">
                                        <span class="text-muted-foreground">Hotspots:</span>
                                        <span class="font-semibold">{{ outbreakZones.length }}</span>
                                    </div>
                                    <div class="flex justify-between">
                                        <span class="text-muted-foreground">Diseases Tracked:</span>
                                        <span class="font-semibold">{{ topDiseases.length }}</span>
                                    </div>
                                </CardContent>
                            </Card>
                        </div>
                    </div>
                </CardContent>
            </Card>
        </div>
    </AppLayout>
</template>

<style scoped>
.custom-marker {
    background: transparent !important;
    border: none !important;
}
</style>
















