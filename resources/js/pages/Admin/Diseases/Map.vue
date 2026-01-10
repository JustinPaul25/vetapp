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

// Format date string (YYYY-MM-DD) as local date to avoid timezone issues
const formatDate = (dateString: string | null) => {
    if (!dateString) return '';
    // Parse date string (YYYY-MM-DD) as local date to avoid timezone issues
    const [year, month, day] = dateString.split('-').map(Number);
    const date = new Date(year, month - 1, day);
    return date.toLocaleDateString();
};

// Group cases by location (round coordinates to handle slight variations)
interface GroupedLocation {
    lat: number;
    lng: number;
    address: string;
    diseases: Array<{
        disease_id: number;
        disease_name: string;
        appointment_date: string | null;
    }>;
}

const groupCasesByLocation = (cases: DiseaseCase[]): GroupedLocation[] => {
    // Use a map with rounded coordinates as key to group nearby cases
    const locationMap = new Map<string, GroupedLocation>();
    const tolerance = 0.0001; // Small tolerance for coordinate differences
    
    cases.forEach((caseItem) => {
        // Round coordinates to group nearby cases
        const roundedLat = Math.round(caseItem.lat / tolerance) * tolerance;
        const roundedLng = Math.round(caseItem.lng / tolerance) * tolerance;
        const key = `${roundedLat.toFixed(6)},${roundedLng.toFixed(6)}`;
        
        if (!locationMap.has(key)) {
            locationMap.set(key, {
                lat: roundedLat,
                lng: roundedLng,
                address: caseItem.address,
                diseases: []
            });
        }
        
        const location = locationMap.get(key)!;
        // Add disease if not already added (to handle duplicates)
        const existingDisease = location.diseases.find(
            d => d.disease_id === caseItem.disease_id && 
                 d.appointment_date === caseItem.appointment_date
        );
        
        if (!existingDisease) {
            location.diseases.push({
                disease_id: caseItem.disease_id,
                disease_name: caseItem.disease_name,
                appointment_date: caseItem.appointment_date
            });
        }
    });
    
    return Array.from(locationMap.values());
};

// Generate a unique popup ID
const generatePopupId = (index: number, lat: number, lng: number): string => {
    return `popup_${index}_${Math.abs(lat).toFixed(6)}_${Math.abs(lng).toFixed(6)}`.replace(/\./g, '_').replace(/[^a-zA-Z0-9_]/g, '_');
};

// Generate popup content with expandable disease list
const generatePopupContent = (location: GroupedLocation, diseaseColors: Record<string, string>, index: number): string => {
    const uniqueDiseases = Array.from(
        new Map(location.diseases.map(d => [d.disease_name, d])).values()
    );
    // Create a unique ID using index and coordinates (sanitized)
    const popupId = generatePopupId(index, location.lat, location.lng);
    const hasMultipleDiseases = uniqueDiseases.length > 1;
    const firstDisease = uniqueDiseases[0];
    const remainingDiseases = uniqueDiseases.slice(1);
    
    let content = `
        <div class="disease-popup" style="min-width: 200px;">
            <div class="disease-item" style="margin-bottom: 8px;">
                <strong style="color: ${diseaseColors[firstDisease.disease_name] || '#3388ff'};">
                    ${firstDisease.disease_name}
                </strong>
            </div>
    `;
    
    if (hasMultipleDiseases) {
        content += `
            <div id="${popupId}_more" style="display: none;">
        `;
        
        remainingDiseases.forEach((disease) => {
            content += `
                <div class="disease-item" style="margin-bottom: 8px;">
                    <strong style="color: ${diseaseColors[disease.disease_name] || '#3388ff'};">
                        ${disease.disease_name}
                    </strong>
                </div>
            `;
        });
        
        content += `</div>`;
        content += `
            <div style="margin-top: 8px; border-top: 1px solid #e5e7eb; padding-top: 8px;">
                <a href="#" 
                   id="${popupId}_toggle" 
                   onclick="
                       const moreDiv = document.getElementById('${popupId}_more');
                       const toggleLink = document.getElementById('${popupId}_toggle');
                       if (moreDiv.style.display === 'none') {
                           moreDiv.style.display = 'block';
                           toggleLink.textContent = 'Hide';
                       } else {
                           moreDiv.style.display = 'none';
                           toggleLink.textContent = 'See more (${remainingDiseases.length} more)';
                       }
                       return false;
                   "
                   style="color: #3b82f6; text-decoration: none; font-size: 12px; cursor: pointer;"
                   onmouseover="this.style.textDecoration='underline'"
                   onmouseout="this.style.textDecoration='none'">
                    See more (${remainingDiseases.length} more)
                </a>
            </div>
        `;
    }
    
    content += `
            <div style="margin-top: 8px; font-size: 11px; color: #6b7280;">
                Address: ${location.address}
            </div>
        </div>
    `;
    
    return content;
};

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

    // Add hotspot zones
    props.outbreakZones.forEach((zone) => {
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
            )
            .bindTooltip(`Hotspot: ${zone.address}`, {
                permanent: false,
                direction: 'top',
                offset: [0, -6]
            });
    });

    // Group cases by location
    const groupedLocations = groupCasesByLocation(props.cases);
    
    // Add disease case markers (one per location)
    groupedLocations.forEach((location, index) => {
        const uniqueDiseases = Array.from(
            new Map(location.diseases.map(d => [d.disease_name, d])).values()
        );
        const firstDisease = uniqueDiseases[0];
        const color = props.diseaseColors[firstDisease.disease_name] || '#3388ff';
        
        // Create custom colored marker
        const customIcon = L.divIcon({
            className: 'custom-marker',
            html: `<div style="background-color: ${color}; width: 12px; height: 12px; border-radius: 50%; border: 2px solid white; box-shadow: 0 2px 4px rgba(0,0,0,0.3);"></div>`,
            iconSize: [12, 12],
            iconAnchor: [6, 6],
        });

        // Generate popup content with expandable disease list
        const popupContent = generatePopupContent(location, props.diseaseColors, index);
        
        // Create tooltip text - show first disease and indicator for multiple
        let tooltipText = firstDisease.disease_name;
        if (uniqueDiseases.length > 1) {
            tooltipText += ` (+${uniqueDiseases.length - 1} more)`;
        }

        const marker = L.marker([location.lat, location.lng], { icon: customIcon })
            .addTo(map.value!)
            .bindPopup(popupContent, {
                maxWidth: 300,
                className: 'disease-popup-container'
            })
            .bindTooltip(tooltipText, {
                permanent: false,
                direction: 'top',
                offset: [0, -6]
            });
        
        // Attach event listener to handle popup open for proper rendering of expandable content
        marker.on('popupopen', () => {
            // Small delay to ensure popup content is rendered
            setTimeout(() => {
                const popupId = generatePopupId(index, location.lat, location.lng);
                const moreDiv = document.getElementById(`${popupId}_more`);
                if (moreDiv) {
                    moreDiv.style.display = 'none';
                }
                const toggleLink = document.getElementById(`${popupId}_toggle`);
                if (toggleLink && uniqueDiseases.length > 1) {
                    toggleLink.textContent = `See more (${uniqueDiseases.length - 1} more)`;
                }
            }, 100);
        });
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

<style>
/* Global styles for Leaflet popups */
.disease-popup-container .leaflet-popup-content {
    margin: 12px;
    font-size: 14px;
}

.disease-popup {
    font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
}

.disease-item {
    padding: 4px 0;
}

.disease-popup a {
    display: inline-block;
    margin-top: 4px;
}

.disease-popup a:hover {
    opacity: 0.8;
}
</style>

















