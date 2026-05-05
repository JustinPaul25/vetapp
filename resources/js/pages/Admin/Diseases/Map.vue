<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, router } from '@inertiajs/vue3';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { MapPin, AlertCircle, Play, Pause } from 'lucide-vue-next';
import { computed, nextTick, onMounted, onUnmounted, ref, watch } from 'vue';
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
    barangay?: string;
    appointment_date: string | null;
}

interface TopDisease {
    name: string;
    count: number;
}

interface BarangayWithCases {
    barangay: string;
    count: number;
}

interface Props {
    outbreakZones: OutbreakZone[];
    cases: DiseaseCase[];
    topDiseases: TopDisease[];
    diseaseColors: Record<string, string>;
    allDiseases?: Array<{ id: number; name: string }>;
    barangaysWithCases?: BarangayWithCases[];
    totalCases?: number;
    filteredCases?: number;
    conditionFilter?: string | null;
    diseaseFilter?: string | null;
}

const CONDITION_OPTIONS = [
    { value: 'all', label: 'All' },
    { value: 'Died', label: 'Died' },
    { value: 'Recovered', label: 'Recovered' },
    { value: 'Under Treatment', label: 'Under Treatment' },
] as const;

const props = defineProps<Props>();

const breadcrumbs = [
    { title: 'Dashboard', href: dashboard().url },
    { title: 'Disease Map', href: '#' },
];

const mapContainer = ref<HTMLElement | null>(null);
const map = ref<L.Map | null>(null);
const diseaseMarkersLayer = ref<L.LayerGroup | null>(null);
const selectedDisease = ref<string | null>(null);

const map2Container = ref<HTMLElement | null>(null);
const map2 = ref<L.Map | null>(null);
const individualMarkersLayer = ref<L.LayerGroup | null>(null);
const hotspotMarkersLayer = ref<L.LayerGroup | null>(null);

const LIST_COLLAPSE_DISEASES = 5;
const LIST_COLLAPSE_HOTSPOTS = 8;
const showAllDiseases = ref(false);
const showAllHotspots = ref(false);

function applyConditionFilter(value: string) {
    const condition = value === 'all' ? undefined : value;
    const params: Record<string, string> = {};
    if (condition) params.condition = condition;
    if (props.diseaseFilter && props.diseaseFilter !== 'all') params.disease = props.diseaseFilter;
    router.get('/admin/diseases/map', params, { preserveState: false });
}

function applyDiseaseFilter(value: string) {
    const disease = value === 'all' ? undefined : value;
    const params: Record<string, string> = {};
    if (props.conditionFilter) params.condition = props.conditionFilter;
    if (disease) params.disease = disease;
    router.get('/admin/diseases/map', params, { preserveState: false });
}

// Format date string (YYYY-MM-DD) as local date to avoid timezone issues
const formatDate = (dateString: string | null) => {
    if (!dateString) return '';
    // Parse date string (YYYY-MM-DD) as local date to avoid timezone issues
    const [year, month, day] = dateString.split('-').map(Number);
    const date = new Date(year, month - 1, day);
    return date.toLocaleDateString();
};

function aggregateZones(cases: DiseaseCase[]): OutbreakZone[] {
    const byAddress = new Map<string, DiseaseCase[]>();
    for (const c of cases) {
        const addr = c.address || 'Unknown';
        if (!byAddress.has(addr)) byAddress.set(addr, []);
        byAddress.get(addr)!.push(c);
    }
    const rows: OutbreakZone[] = [];
    byAddress.forEach((arr, address) => {
        const count = arr.length;
        if (count === 0) return;
        const latSum = arr.reduce((s, x) => s + Number(x.lat), 0);
        const lngSum = arr.reduce((s, x) => s + Number(x.lng), 0);
        rows.push({
            address,
            lat: latSum / count,
            lng: lngSum / count,
            count,
        });
    });
    return rows.sort((a, b) => b.count - a.count);
}

const timelineDates = computed(() => {
    const set = new Set<string>();
    for (const c of props.cases) {
        if (c.appointment_date) set.add(c.appointment_date);
    }
    return Array.from(set).sort();
});

const timelineMaxIdx = computed(() => Math.max(0, timelineDates.value.length - 1));

const timelineIndex = ref(0);

watch(
    timelineDates,
    (dates) => {
        timelineIndex.value = dates.length > 0 ? dates.length - 1 : 0;
    },
    { immediate: true },
);

const effectiveCases = computed(() => {
    const dates = timelineDates.value;
    if (dates.length === 0) return props.cases;
    const idx = Math.min(Math.max(0, timelineIndex.value), dates.length - 1);
    const cutoff = dates[idx]!;
    return props.cases.filter((c) => {
        if (!c.appointment_date) return idx >= dates.length - 1;
        return c.appointment_date <= cutoff;
    });
});

const effectiveZones = computed(() => aggregateZones(effectiveCases.value));

const displayedDiseases = computed(() => {
    const list = props.topDiseases;
    if (showAllDiseases.value || list.length <= LIST_COLLAPSE_DISEASES) return list;
    return list.slice(0, LIST_COLLAPSE_DISEASES);
});

const displayedHotspots = computed(() => {
    const list = effectiveZones.value;
    if (showAllHotspots.value || list.length <= LIST_COLLAPSE_HOTSPOTS) return list;
    return list.slice(0, LIST_COLLAPSE_HOTSPOTS);
});

const hasTimeline = computed(() => timelineDates.value.length > 0);

const timelineLabel = computed(() => {
    if (!hasTimeline.value) return '';
    const dates = timelineDates.value;
    const d = dates[Math.min(timelineIndex.value, dates.length - 1)];
    return d ? formatDate(d) : '';
});

let playInterval: ReturnType<typeof setInterval> | null = null;
const isPlaying = ref(false);

function stopPlayback() {
    if (playInterval !== null) {
        clearInterval(playInterval);
        playInterval = null;
    }
    isPlaying.value = false;
}

function toggleTimelinePlay() {
    if (!hasTimeline.value) return;
    if (isPlaying.value) {
        stopPlayback();
        return;
    }
    isPlaying.value = true;
    timelineIndex.value = 0;
    playInterval = setInterval(() => {
        if (timelineIndex.value >= timelineMaxIdx.value) {
            stopPlayback();
            return;
        }
        timelineIndex.value += 1;
    }, 550);
}

onUnmounted(() => {
    stopPlayback();
});

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
        const lat = Number(caseItem.lat);
        const lng = Number(caseItem.lng);
        if (!Number.isFinite(lat) || !Number.isFinite(lng) || Number.isNaN(lat) || Number.isNaN(lng)) return;
        const roundedLat = Math.round(lat / tolerance) * tolerance;
        const roundedLng = Math.round(lng / tolerance) * tolerance;
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

const HOTSPOT_TOLERANCE = 0.001;

function buildHotspotMap(zones: OutbreakZone[]): Map<string, { lat: number; lng: number; address: string }> {
    const m = new Map<string, { lat: number; lng: number; address: string }>();
    zones.forEach((zone) => {
        const lat = Number(zone.lat);
        const lng = Number(zone.lng);
        if (!Number.isFinite(lat) || !Number.isFinite(lng)) return;
        const key = `${Math.round(lat / HOTSPOT_TOLERANCE) * HOTSPOT_TOLERANCE},${Math.round(lng / HOTSPOT_TOLERANCE) * HOTSPOT_TOLERANCE}`;
        m.set(key, { lat, lng, address: zone.address });
    });
    return m;
}

function isNearHotspot(
    lat: number,
    lng: number,
    hotspotMap: Map<string, { lat: number; lng: number; address: string }>
): boolean {
    for (const [, hotspot] of hotspotMap.entries()) {
        const d = Math.sqrt(Math.pow(lat - hotspot.lat, 2) + Math.pow(lng - hotspot.lng, 2));
        if (d < HOTSPOT_TOLERANCE) return true;
    }
    return false;
}

// Fix for default marker icons in Leaflet with Vite
delete (L.Icon.Default.prototype as any)._getIconUrl;
L.Icon.Default.mergeOptions({
    iconRetinaUrl: 'https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/images/marker-icon-2x.png',
    iconUrl: 'https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/images/marker-icon.png',
    shadowUrl: 'https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/images/marker-shadow.png',
});

function renderDiseaseMarkers(
    casesToShow: DiseaseCase[],
    hotspotMap: Map<string, { lat: number; lng: number; address: string }>,
    fitToMarkers: boolean
) {
    const m = map.value;
    const layer = diseaseMarkersLayer.value;
    if (!m || !layer) return;

    layer.clearLayers();
    const grouped = groupCasesByLocation(casesToShow);
    const bounds: L.LatLng[] = [];

    const minSize = 10;
    const maxSize = 28;
    const maxCount = Math.max(1, ...grouped.map((loc) => loc.diseases.length));

    grouped.forEach((location, index) => {
        if (isNearHotspot(location.lat, location.lng, hotspotMap)) return;
        const lat = Number(location.lat);
        const lng = Number(location.lng);
        if (!Number.isFinite(lat) || !Number.isFinite(lng)) return;

        const uniqueDiseases = Array.from(
            new Map(location.diseases.map((d) => [d.disease_name, d])).values()
        );
        const first = uniqueDiseases[0];
        const color = props.diseaseColors[first.disease_name] || '#3388ff';

        const count = location.diseases.length;
        const t = maxCount <= 1 ? 1 : (count - 1) / (maxCount - 1);
        const size = Math.round(minSize + t * (maxSize - minSize));
        const anchor = Math.round(size / 2);

        const icon = L.divIcon({
            className: 'custom-marker',
            html: `<div style="background-color: ${color}; width: ${size}px; height: ${size}px; border-radius: 50%; border: 2px solid white; box-shadow: 0 2px 4px rgba(0,0,0,0.3);"></div>`,
            iconSize: [size, size],
            iconAnchor: [anchor, anchor],
        });

        const popupContent = generatePopupContent(location, props.diseaseColors, index);
        let tooltipText = first.disease_name;
        if (uniqueDiseases.length > 1) tooltipText += ` (+${uniqueDiseases.length - 1} more)`;

        const marker = L.marker([lat, lng], { icon })
            .addTo(layer as L.LayerGroup)
            .bindPopup(popupContent, { maxWidth: 300, className: 'disease-popup-container' })
            .bindTooltip(tooltipText, { permanent: false, direction: 'top', offset: [0, -6] });

        marker.on('popupopen', () => {
            setTimeout(() => {
                const popupId = generatePopupId(index, location.lat, location.lng);
                const moreDiv = document.getElementById(`${popupId}_more`);
                if (moreDiv) moreDiv.style.display = 'none';
                const toggleLink = document.getElementById(`${popupId}_toggle`);
                if (toggleLink && uniqueDiseases.length > 1)
                    toggleLink.textContent = `See more (${uniqueDiseases.length - 1} more)`;
            }, 100);
        });

        bounds.push(L.latLng(lat, lng));
    });

    if (fitToMarkers && bounds.length > 0) {
        m.fitBounds(L.latLngBounds(bounds), { maxZoom: 15, padding: [24, 24] });
    }
}

function renderIndividualDiseaseMarkers(casesToShow: DiseaseCase[], fitToMarkers: boolean) {
    const m = map2.value;
    const layer = individualMarkersLayer.value;
    if (!m || !layer) return;

    layer.clearLayers();
    const bounds: L.LatLng[] = [];
    const size = 14;
    const anchor = Math.round(size / 2);

    casesToShow.forEach((caseItem) => {
        const lat = Number(caseItem.lat);
        const lng = Number(caseItem.lng);
        if (!Number.isFinite(lat) || !Number.isFinite(lng) || Number.isNaN(lat) || Number.isNaN(lng)) return;

        const color = props.diseaseColors[caseItem.disease_name] || '#3388ff';
        const icon = L.divIcon({
            className: 'custom-marker individual-marker',
            html: `<div style="background-color: ${color}; width: ${size}px; height: ${size}px; border-radius: 50%; border: 2px solid white; box-shadow: 0 2px 4px rgba(0,0,0,0.3);"></div>`,
            iconSize: [size, size],
            iconAnchor: [anchor, anchor],
        });

        const popupContent = `
            <div class="disease-popup" style="min-width: 180px;">
                <div class="disease-item" style="margin-bottom: 6px;">
                    <strong style="color: ${color};">${caseItem.disease_name}</strong>
                </div>
                <div style="margin-top: 6px; font-size: 11px; color: #6b7280;">
                    Address: ${caseItem.address}
                </div>
                ${caseItem.appointment_date ? `<div style="margin-top: 4px; font-size: 11px; color: #6b7280;">Date: ${formatDate(caseItem.appointment_date)}</div>` : ''}
            </div>
        `;

        const marker = L.marker([lat, lng], { icon })
            .addTo(layer as L.LayerGroup)
            .bindPopup(popupContent, { maxWidth: 300, className: 'disease-popup-container' })
            .bindTooltip(caseItem.disease_name, { permanent: false, direction: 'top', offset: [0, -6] });

        bounds.push(L.latLng(lat, lng));
    });

    if (fitToMarkers && bounds.length > 0) {
        m.fitBounds(L.latLngBounds(bounds), { maxZoom: 15, padding: [24, 24] });
    }
}

function renderHotspotMarkers(zones: OutbreakZone[], casesForLookup: DiseaseCase[]) {
    const layer = hotspotMarkersLayer.value;
    if (!layer) return;

    layer.clearLayers();
    if (zones.length === 0) return;

    const minHotspotSize = 16;
    const maxHotspotSize = 44;
    const maxHotspotCount = Math.max(1, ...zones.map((z) => Number(z.count) || 0));

    zones.forEach((zone) => {
        const lat = Number(zone.lat);
        const lng = Number(zone.lng);
        if (!Number.isFinite(lat) || !Number.isFinite(lng)) return;

        const hotspotKey = `${Math.round(lat / HOTSPOT_TOLERANCE) * HOTSPOT_TOLERANCE},${Math.round(lng / HOTSPOT_TOLERANCE) * HOTSPOT_TOLERANCE}`;

        const count = Number(zone.count) || 0;
        const t = maxHotspotCount <= 1 ? 1 : (count - 1) / (maxHotspotCount - 1);
        const size = Math.round(minHotspotSize + t * (maxHotspotSize - minHotspotSize));
        const anchor = Math.round(size / 2);
        const border = Math.max(2, Math.round(size * 0.15));
        const innerSize = Math.max(4, Math.round(size * 0.4));

        const redMarkerIcon = L.divIcon({
            className: 'hotspot-marker',
            html: `
                <div style="
                    background-color: #dc2626;
                    width: ${size}px;
                    height: ${size}px;
                    border-radius: 50%;
                    border: ${border}px solid white;
                    box-shadow: 0 3px 10px rgba(0,0,0,0.6), 0 0 0 3px rgba(220, 38, 38, 0.4);
                    position: relative;
                    z-index: 1000;
                ">
                    <div style="
                        position: absolute;
                        top: 50%;
                        left: 50%;
                        transform: translate(-50%, -50%);
                        width: ${innerSize}px;
                        height: ${innerSize}px;
                        background-color: white;
                        border-radius: 50%;
                        box-shadow: inset 0 1px 2px rgba(0,0,0,0.2);
                    "></div>
                </div>
            `,
            iconSize: [size, size],
            iconAnchor: [anchor, anchor],
            popupAnchor: [0, -anchor],
        });

        const hotspotMarker = L.marker([lat, lng], {
            icon: redMarkerIcon,
            zIndexOffset: 1000,
        });

        const hotspotDiseases = casesForLookup
            .filter((caseItem) => {
                const caseKey = `${Math.round(caseItem.lat / HOTSPOT_TOLERANCE) * HOTSPOT_TOLERANCE},${Math.round(caseItem.lng / HOTSPOT_TOLERANCE) * HOTSPOT_TOLERANCE}`;
                return caseKey === hotspotKey || caseItem.address === zone.address;
            })
            .map((c) => c.disease_name);

        const uniqueHotspotDiseases = Array.from(new Set(hotspotDiseases));

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
            uniqueHotspotDiseases.forEach((disease) => {
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
            .addTo(layer as L.LayerGroup)
            .bindPopup(hotspotPopupContent, {
                maxWidth: 300,
                className: 'hotspot-popup-container',
            })
            .bindTooltip(`🔥 Hotspot: ${zone.address} (${zone.count} cases)`, {
                permanent: false,
                direction: 'top',
                offset: [0, -6],
            });
    });
}

function syncMainMapLayers(fitDiseaseMarkers: boolean) {
    const zones = effectiveZones.value;
    const hotspotMap = buildHotspotMap(zones);
    const filteredCases = selectedDisease.value
        ? effectiveCases.value.filter((c) => c.disease_name === selectedDisease.value)
        : effectiveCases.value;
    renderHotspotMarkers(zones, effectiveCases.value);
    renderDiseaseMarkers(filteredCases, hotspotMap, fitDiseaseMarkers);
}

onMounted(() => {
    if (!mapContainer.value) return;

    // Initialize map centered on Panabo City
    map.value = L.map(mapContainer.value).setView([7.3075, 125.6830], 13);

    // Add OpenStreetMap tile layer
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '© OpenStreetMap contributors',
        maxZoom: 19,
    }).addTo(map.value as any);

    const hotspotLayer = L.layerGroup().addTo(map.value! as L.Map);
    hotspotMarkersLayer.value = hotspotLayer;

    const layerGroup = L.layerGroup();
    layerGroup.addTo(map.value! as any);
    diseaseMarkersLayer.value = layerGroup;

    syncMainMapLayers(false);
});

watch([timelineIndex, effectiveCases, effectiveZones], () => {
    if (!map.value || !diseaseMarkersLayer.value || !hotspotMarkersLayer.value) return;
    syncMainMapLayers(false);
    if (selectedDisease.value && map2.value && individualMarkersLayer.value) {
        const filteredCases = effectiveCases.value.filter((c) => c.disease_name === selectedDisease.value);
        renderIndividualDiseaseMarkers(filteredCases, false);
    }
});

watch(
    () => [props.conditionFilter, props.diseaseFilter] as const,
    () => {
        showAllDiseases.value = false;
        showAllHotspots.value = false;
        nextTick(() => {
            timelineIndex.value = timelineMaxIdx.value;
        });
    },
);

function initMap2() {
    if (!map2Container.value) return;
    map2.value = L.map(map2Container.value).setView([7.3075, 125.6830], 13);
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '© OpenStreetMap contributors',
        maxZoom: 19,
    }).addTo(map2.value as any);
    const layerGroup2 = L.layerGroup();
    layerGroup2.addTo(map2.value! as any);
    individualMarkersLayer.value = layerGroup2;
}

function destroyMap2() {
    if (map2.value) {
        map2.value.remove();
        map2.value = null;
    }
    individualMarkersLayer.value = null;
}

watch(selectedDisease, async () => {
    const filteredCases = selectedDisease.value
        ? effectiveCases.value.filter((c) => c.disease_name === selectedDisease.value)
        : effectiveCases.value;

    if (selectedDisease.value) {
        if (map.value && diseaseMarkersLayer.value && hotspotMarkersLayer.value) {
            syncMainMapLayers(true);
        }
        await nextTick();
        if (!map2.value && map2Container.value) {
            initMap2();
        }
        if (map2.value && individualMarkersLayer.value) {
            renderIndividualDiseaseMarkers(filteredCases, true);
            map2.value.invalidateSize();
        }
    } else {
        destroyMap2();
        if (map.value && diseaseMarkersLayer.value && hotspotMarkersLayer.value) {
            syncMainMapLayers(true);
        }
        await nextTick();
        map.value?.invalidateSize?.();
    }
});

function selectDisease(name: string) {
    selectedDisease.value = selectedDisease.value === name ? null : name;
}
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
                    <div class="mb-4 flex flex-wrap items-center gap-3">
                        <label class="text-sm font-medium text-muted-foreground">Filter by Condition:</label>
                        <select
                            :value="conditionFilter ?? 'all'"
                            class="rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"
                            @change="applyConditionFilter(($event.target as HTMLSelectElement).value)"
                        >
                            <option
                                v-for="opt in CONDITION_OPTIONS"
                                :key="opt.value"
                                :value="opt.value"
                            >
                                {{ opt.label }}
                            </option>
                        </select>
                        <label class="text-sm font-medium text-muted-foreground">Filter by Disease:</label>
                        <select
                            :value="diseaseFilter ?? 'all'"
                            class="rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"
                            @change="applyDiseaseFilter(($event.target as HTMLSelectElement).value)"
                        >
                            <option value="all">All Diseases</option>
                            <option
                                v-for="d in (allDiseases ?? [])"
                                :key="d.id"
                                :value="d.name"
                            >
                                {{ d.name }}
                            </option>
                        </select>
                        <span class="text-sm text-muted-foreground">
                            Showing {{ filteredCases ?? cases.length }} of {{ totalCases ?? cases.length }} cases
                        </span>
                    </div>
                    <div class="grid grid-cols-1 lg:grid-cols-4 gap-6">
                        <!-- Map: Disease Map when nothing selected, Individual Cases Map when a Top Disease is selected -->
                        <div class="lg:col-span-3 min-h-[600px]">
                            <div
                                v-show="!selectedDisease"
                                ref="mapContainer"
                                class="w-full h-[600px] rounded-lg border"
                                style="z-index: 1"
                            ></div>
                            <div
                                v-if="selectedDisease"
                                ref="map2Container"
                                class="w-full h-[600px] rounded-lg border"
                                style="z-index: 1"
                            ></div>
                        </div>

                        <!-- Legend and Stats -->
                        <div class="space-y-4">
                            <!-- Diseases (full list, collapsible) -->
                            <Card>
                                <CardHeader class="pb-3">
                                    <CardTitle class="text-sm flex items-center gap-2">
                                        <AlertCircle class="h-4 w-4" />
                                        Diseases
                                    </CardTitle>
                                </CardHeader>
                                <CardContent class="space-y-2">
                                    <button
                                        v-for="disease in displayedDiseases"
                                        :key="disease.name"
                                        type="button"
                                        class="flex w-full items-center gap-2 rounded-lg px-2 py-1.5 text-left text-sm transition-colors hover:bg-muted/80 focus:outline-none focus-visible:ring-2 focus-visible:ring-ring"
                                        :class="{ 'bg-muted ring-2 ring-primary': selectedDisease === disease.name }"
                                        @click="selectDisease(disease.name)"
                                    >
                                        <div
                                            class="h-4 w-4 shrink-0 rounded-full border-2 border-white shadow-sm"
                                            :style="{
                                                backgroundColor: diseaseColors[disease.name] || '#3388ff',
                                            }"
                                        />
                                        <span class="min-w-0 flex-1 truncate font-medium">{{ disease.name }}</span>
                                        <span class="shrink-0 text-muted-foreground font-semibold">
                                            {{ disease.count }}
                                        </span>
                                    </button>
                                    <button
                                        v-if="topDiseases.length > LIST_COLLAPSE_DISEASES"
                                        type="button"
                                        class="w-full rounded-md py-1.5 text-left text-sm font-medium text-primary hover:underline focus:outline-none focus-visible:ring-2 focus-visible:ring-ring"
                                        @click="showAllDiseases = !showAllDiseases"
                                    >
                                        {{ showAllDiseases ? 'Show less' : `See all (${topDiseases.length})` }}
                                    </button>
                                    <div v-if="topDiseases.length === 0" class="text-sm text-muted-foreground">
                                        No disease data available
                                    </div>
                                </CardContent>
                            </Card>

                            <!-- Barangays with Cases (when filtering by disease) -->
                            <Card v-if="diseaseFilter && diseaseFilter !== 'all'">
                                <CardHeader class="pb-3">
                                    <CardTitle class="text-sm">
                                        Barangays with {{ diseaseFilter }} Cases
                                    </CardTitle>
                                    <CardDescription>
                                        Locations where cases have been reported
                                    </CardDescription>
                                </CardHeader>
                                <CardContent class="space-y-2">
                                    <div
                                        v-for="(item, index) in (barangaysWithCases ?? [])"
                                        :key="index"
                                        class="text-sm"
                                    >
                                        <div class="font-medium truncate">{{ item.barangay }}</div>
                                        <div class="text-muted-foreground">
                                            {{ item.count }} case{{ item.count !== 1 ? 's' : '' }}
                                        </div>
                                    </div>
                                    <div v-if="!barangaysWithCases?.length" class="text-sm text-muted-foreground">
                                        No barangay data for this disease
                                    </div>
                                </CardContent>
                            </Card>

                            <!-- Hotspots (time slider + collapsible list) -->
                            <Card>
                                <CardHeader class="pb-3">
                                    <CardTitle class="text-sm">Hotspots</CardTitle>
                                    <CardDescription v-if="hasTimeline" class="text-xs">
                                        Drag the slider or play to see how cumulative cases grow by appointment date.
                                    </CardDescription>
                                </CardHeader>
                                <CardContent class="space-y-3">
                                    <div
                                        v-if="hasTimeline"
                                        class="space-y-2 rounded-md border border-border bg-muted/30 p-3"
                                    >
                                        <div class="flex items-center justify-between gap-2">
                                            <span class="text-xs font-medium text-muted-foreground">Cases through</span>
                                            <span class="text-xs font-semibold tabular-nums">{{ timelineLabel }}</span>
                                        </div>
                                        <input
                                            v-model.number="timelineIndex"
                                            type="range"
                                            :min="0"
                                            :max="timelineMaxIdx"
                                            class="h-2 w-full cursor-pointer accent-primary"
                                            :aria-valuemin="0"
                                            :aria-valuemax="timelineMaxIdx"
                                            :aria-valuenow="timelineIndex"
                                            aria-label="Hotspot timeline"
                                        />
                                        <button
                                            type="button"
                                            class="inline-flex w-full items-center justify-center gap-2 rounded-md border border-input bg-background px-3 py-2 text-xs font-medium shadow-sm transition-colors hover:bg-muted/80 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"
                                            @click="toggleTimelinePlay"
                                        >
                                            <Play v-if="!isPlaying" class="h-3.5 w-3.5 shrink-0" />
                                            <Pause v-else class="h-3.5 w-3.5 shrink-0" />
                                            {{ isPlaying ? 'Pause' : 'Play over time' }}
                                        </button>
                                    </div>
                                    <p v-else class="text-xs text-muted-foreground">
                                        No appointment dates in this view — hotspots show all cases together.
                                    </p>
                                    <div class="space-y-2">
                                        <div
                                            v-for="(zone, index) in displayedHotspots"
                                            :key="zone.address + '-' + index"
                                            class="text-sm"
                                        >
                                            <div class="font-medium truncate">{{ zone.address }}</div>
                                            <div class="text-muted-foreground">
                                                {{ zone.count }} case{{ zone.count !== 1 ? 's' : '' }}
                                            </div>
                                        </div>
                                        <button
                                            v-if="effectiveZones.length > LIST_COLLAPSE_HOTSPOTS"
                                            type="button"
                                            class="w-full rounded-md py-1.5 text-left text-sm font-medium text-primary hover:underline focus:outline-none focus-visible:ring-2 focus-visible:ring-ring"
                                            @click="showAllHotspots = !showAllHotspots"
                                        >
                                            {{
                                                showAllHotspots
                                                    ? 'Show less'
                                                    : `See all (${effectiveZones.length})`
                                            }}
                                        </button>
                                        <div v-if="effectiveZones.length === 0" class="text-sm text-muted-foreground">
                                            No hotspots detected
                                        </div>
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
                                        <span class="font-semibold">{{ totalCases ?? cases.length }}</span>
                                    </div>
                                    <div v-if="conditionFilter" class="flex justify-between">
                                        <span class="text-muted-foreground">Filtered Cases:</span>
                                        <span class="font-semibold">{{ filteredCases ?? cases.length }}</span>
                                    </div>
                                    <div class="flex justify-between">
                                        <span class="text-muted-foreground">Hotspots:</span>
                                        <span class="font-semibold">{{ effectiveZones.length }}</span>
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

/* Keep marker stacking only; Leaflet controls absolute positioning */
.custom-marker {
    z-index: 100;
}
</style>

















