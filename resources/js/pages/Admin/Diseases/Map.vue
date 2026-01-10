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

    // Create a map of hotspot locations to avoid placing disease markers on them
    const hotspotLocations = new Map<string, { lat: number; lng: number; address: string }>();
    const hotspotTolerance = 0.001; // Tolerance for matching hotspot locations (increased for better matching)
    const hotspotMarkers: L.Marker[] = []; // Store hotspot markers to bring them to front later
    
    // Add hotspot zones first (we'll bring them to front after all markers are added)
    props.outbreakZones.forEach((zone) => {
        const hotspotKey = `${Math.round(zone.lat / hotspotTolerance) * hotspotTolerance},${Math.round(zone.lng / hotspotTolerance) * hotspotTolerance}`;
        hotspotLocations.set(hotspotKey, { lat: zone.lat, lng: zone.lng, address: zone.address });
        
        // Create a custom red marker icon (larger, more visible red circle)
        const redMarkerIcon = L.divIcon({
            className: 'hotspot-marker',
            html: `
                <div style="
                    background-color: #dc2626;
                    width: 24px;
                    height: 24px;
                    border-radius: 50%;
                    border: 4px solid white;
                    box-shadow: 0 3px 10px rgba(0,0,0,0.6), 0 0 0 3px rgba(220, 38, 38, 0.4);
                    position: relative;
                    z-index: 1000;
                ">
                    <div style="
                        position: absolute;
                        top: 50%;
                        left: 50%;
                        transform: translate(-50%, -50%);
                        width: 10px;
                        height: 10px;
                        background-color: white;
                        border-radius: 50%;
                        box-shadow: inset 0 1px 2px rgba(0,0,0,0.2);
                    "></div>
                </div>
            `,
            iconSize: [24, 24],
            iconAnchor: [12, 12],
            popupAnchor: [0, -12],
        });
        
        // Add red marker for hotspot with higher z-index
        const hotspotMarker = L.marker([zone.lat, zone.lng], {
            icon: redMarkerIcon,
            zIndexOffset: 1000, // Ensure hotspots appear on top
        });
        
        // Collect diseases for this hotspot address
        const hotspotDiseases = props.cases
            .filter(caseItem => {
                const caseKey = `${Math.round(caseItem.lat / hotspotTolerance) * hotspotTolerance},${Math.round(caseItem.lng / hotspotTolerance) * hotspotTolerance}`;
                return caseKey === hotspotKey || caseItem.address === zone.address;
            })
            .map(c => c.disease_name);
        
        const uniqueHotspotDiseases = Array.from(new Set(hotspotDiseases));
        
        // Build popup content for hotspot
        let hotspotPopupContent = `
            <div style="min-width: 200px;">
                <strong style="color: #dc2626;">🔥 Hotspot</strong><br/>
                <div style="margin-top: 8px; font-size: 11px; color: #6b7280;">
                    Address: ${zone.address}<br/>
                    Cases: ${zone.count}
                </div>
        `;
        
        if (uniqueHotspotDiseases.length > 0) {
            hotspotPopupContent += `
                <div style="margin-top: 8px; border-top: 1px solid #e5e7eb; padding-top: 8px;">
                    <strong style="font-size: 12px;">Diseases:</strong><br/>
                    <div style="margin-top: 4px;">
            `;
            uniqueHotspotDiseases.forEach((disease, idx) => {
                const color = props.diseaseColors[disease] || '#3388ff';
                hotspotPopupContent += `
                    <div style="margin-bottom: 4px;">
                        <span style="display: inline-block; width: 8px; height: 8px; border-radius: 50%; background-color: ${color}; margin-right: 6px;"></span>
                        <span style="font-size: 12px;">${disease}</span>
                    </div>
                `;
            });
            hotspotPopupContent += `</div></div>`;
        }
        
        hotspotPopupContent += `</div>`;
        
        hotspotMarker
            .addTo(map.value!)
            .bindPopup(hotspotPopupContent, {
                maxWidth: 300,
                className: 'hotspot-popup-container'
            })
            .bindTooltip(`🔥 Hotspot: ${zone.address} (${zone.count} cases)`, {
                permanent: false,
                direction: 'top',
                offset: [0, -6]
            });
        
        // Store marker to bring to front later
        hotspotMarkers.push(hotspotMarker);
    });

    // Group cases by location
    const groupedLocations = groupCasesByLocation(props.cases);
    
    // Helper function to check if a location is near a hotspot
    const isNearHotspot = (lat: number, lng: number): boolean => {
        for (const [key, hotspot] of hotspotLocations.entries()) {
            const distance = Math.sqrt(
                Math.pow(lat - hotspot.lat, 2) + Math.pow(lng - hotspot.lng, 2)
            );
            // If within 0.001 degrees (~100 meters), consider it a hotspot location
            if (distance < hotspotTolerance) {
                return true;
            }
        }
        return false;
    };
    
    // Add disease case markers (one per location), but skip locations that are hotspots
    groupedLocations.forEach((location, index) => {
        // Check if this location is near a hotspot
        if (isNearHotspot(location.lat, location.lng)) {
            // Skip this location as it's already marked as a hotspot
            return;
        }
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
    
    // Bring all hotspot markers to the front to ensure they're visible above disease markers
    hotspotMarkers.forEach(marker => {
        marker.bringToFront();
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

.hotspot-marker {
    background: transparent !important;
    border: none !important;
    z-index: 1000 !important;
}

.leaflet-marker-pane .hotspot-marker {
    z-index: 1000 !important;
}
</style>

<style>
/* Global styles for Leaflet popups */
.disease-popup-container .leaflet-popup-content {
    margin: 12px;
    font-size: 14px;
}

.hotspot-popup-container .leaflet-popup-content {
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

/* Ensure hotspot markers are visible and on top */
.leaflet-marker-icon[src*="marker-icon-red"] {
    z-index: 1000 !important;
}

/* Custom marker styling */
.custom-marker {
    position: relative;
    z-index: 100;
}
</style>

















