<script setup lang="ts">
import { onMounted, ref, watch, onBeforeUnmount } from 'vue';
import L from 'leaflet';
import 'leaflet/dist/leaflet.css';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Loader2 } from 'lucide-vue-next';

interface Props {
    province?: string;
    city?: string;
    barangay?: string;
    street?: string;
    coordinates?: { lat: number | null; lng: number | null } | null;
    height?: string;
    disabled?: boolean;
}

const props = withDefaults(defineProps<Props>(), {
    province: '',
    city: '',
    barangay: '',
    street: '',
    coordinates: null,
    height: '300px',
    disabled: false,
});

const emit = defineEmits<{
    'update:province': [value: string];
    'update:city': [value: string];
    'update:barangay': [value: string];
    'update:street': [value: string];
    'update:coordinates': [value: { lat: number | null; lng: number | null }];
}>();

const mapContainer = ref<HTMLElement | null>(null);
const map = ref<L.Map | null>(null);
const marker = ref<L.Marker | null>(null);
const searchQuery = ref('');
const searchResults = ref<Array<{ display_name: string; lat: string; lon: string }>>([]);
const isSearching = ref(false);
const showResults = ref(false);
const searchContainerRef = ref<HTMLElement | null>(null);
let searchTimeout: ReturnType<typeof setTimeout> | null = null;

// Fix for default marker icons in Leaflet with Vite
delete (L.Icon.Default.prototype as any)._getIconUrl;
L.Icon.Default.mergeOptions({
    iconRetinaUrl: 'https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/images/marker-icon-2x.png',
    iconUrl: 'https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/images/marker-icon.png',
    shadowUrl: 'https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/images/marker-shadow.png',
});

// Default center: Philippines (Manila area)
const defaultCenter: [number, number] = [14.5995, 120.9842];
const defaultZoom = 13;

interface GeocodeResult {
    display_name: string;
    lat: string;
    lon: string;
}

// Search for locations using Nominatim
const searchLocation = async (query: string) => {
    if (!query.trim()) {
        searchResults.value = [];
        showResults.value = false;
        return;
    }

    isSearching.value = true;
    showResults.value = true;

    try {
        const response = await fetch(
            `https://nominatim.openstreetmap.org/search?format=json&q=${encodeURIComponent(query)}&limit=5&countrycodes=ph&addressdetails=1`,
            {
                headers: {
                    'User-Agent': 'VetApp Address Picker',
                },
            }
        );

        if (response.ok) {
            const data = await response.json();
            searchResults.value = data;
        } else {
            searchResults.value = [];
        }
    } catch (error) {
        console.error('Geocoding error:', error);
        searchResults.value = [];
    } finally {
        isSearching.value = false;
    }
};

// Parse address components from geocoding result
const parseAddressComponents = (displayName: string) => {
    const parts = displayName.split(',');
    const trimmed = parts.map(p => p.trim());
    
    // Try to extract province, city, barangay, street
    // This is a simplified parser - you may need to adjust based on actual data structure
    let province = '';
    let city = '';
    let barangay = '';
    let street = '';

    // Reverse order: typically "Street, Barangay, City, Province"
    if (trimmed.length >= 4) {
        province = trimmed[trimmed.length - 1] || '';
        city = trimmed[trimmed.length - 2] || '';
        barangay = trimmed[trimmed.length - 3] || '';
        street = trimmed.slice(0, trimmed.length - 3).join(', ') || '';
    } else if (trimmed.length === 3) {
        province = trimmed[2] || '';
        city = trimmed[1] || '';
        barangay = trimmed[0] || '';
    } else if (trimmed.length === 2) {
        province = trimmed[1] || '';
        city = trimmed[0] || '';
    }

    return { province, city, barangay, street };
};

// Select a location from search results
const selectLocation = (result: GeocodeResult) => {
    const lat = parseFloat(result.lat);
    const lng = parseFloat(result.lon);

    if (!isNaN(lat) && !isNaN(lng)) {
        // Parse address components
        const addressComponents = parseAddressComponents(result.display_name);
        
        // Update address fields
        emit('update:province', addressComponents.province);
        emit('update:city', addressComponents.city);
        emit('update:barangay', addressComponents.barangay);
        emit('update:street', addressComponents.street);

        // Update marker position
        const newLatLng: [number, number] = [lat, lng];

        if (marker.value) {
            marker.value.setLatLng(newLatLng);
        } else if (map.value) {
            marker.value = L.marker(newLatLng, {
                draggable: !props.disabled,
            }).addTo(map.value);

            marker.value.bindPopup('Your Address');
            setupMarkerDragHandler(marker.value);
        }

        // Update map view
        if (map.value) {
            map.value.setView(newLatLng, 15);
        }

        // Emit coordinates
        emit('update:coordinates', { lat, lng });

        // Clear search
        searchQuery.value = '';
        searchResults.value = [];
        showResults.value = false;
    }
};

// Handle search input
const handleSearchInput = () => {
    if (searchTimeout) {
        clearTimeout(searchTimeout);
    }
    
    if (!searchQuery.value.trim()) {
        searchResults.value = [];
        showResults.value = false;
        return;
    }

    searchTimeout = setTimeout(() => {
        searchLocation(searchQuery.value);
    }, 500);
};

// Handle click outside to close search results
const handleClickOutside = (event: MouseEvent) => {
    if (searchContainerRef.value && !searchContainerRef.value.contains(event.target as Node)) {
        showResults.value = false;
    }
};

// Setup marker drag handler
const setupMarkerDragHandler = (markerInstance: L.Marker) => {
    markerInstance.on('dragend', (e: L.DragEndEvent) => {
        const { lat, lng } = e.target.getLatLng();
        emit('update:coordinates', { lat, lng });
    });
};

// Initialize the map
const initializeMap = () => {
    if (!mapContainer.value || map.value) return;

    const initialCenter: [number, number] = props.coordinates?.lat && props.coordinates?.lng
        ? [props.coordinates.lat, props.coordinates.lng]
        : defaultCenter;

    map.value = L.map(mapContainer.value).setView(initialCenter, defaultZoom);

    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '© OpenStreetMap contributors',
        maxZoom: 19,
    }).addTo(map.value);

    if (props.coordinates?.lat && props.coordinates?.lng) {
        marker.value = L.marker([props.coordinates.lat, props.coordinates.lng], {
            draggable: !props.disabled,
        }).addTo(map.value);

        marker.value.bindPopup('Your Address');
        setupMarkerDragHandler(marker.value);
    }

    map.value.on('click', (e: L.LeafletMouseEvent) => {
        if (props.disabled) return;
        
        const { lat, lng } = e.latlng;

        if (marker.value) {
            marker.value.setLatLng([lat, lng]);
        } else {
            marker.value = L.marker([lat, lng], {
                draggable: !props.disabled,
            }).addTo(map.value!);

            marker.value.bindPopup('Your Address');
            setupMarkerDragHandler(marker.value);
        }

        emit('update:coordinates', { lat, lng });
    });
};

let resizeObserver: ResizeObserver | null = null;

onMounted(() => {
    document.addEventListener('click', handleClickOutside);
    
    if (!mapContainer.value) return;

    resizeObserver = new ResizeObserver((entries) => {
        for (const entry of entries) {
            if (entry.contentRect.width > 0 && entry.contentRect.height > 0) {
                if (!map.value) {
                    initializeMap();
                } else {
                    map.value.invalidateSize();
                }
            }
        }
    });
    
    resizeObserver.observe(mapContainer.value);
    initializeMap();
});

watch(
    () => props.coordinates,
    (newValue) => {
        if (!map.value) return;

        if (newValue?.lat && newValue?.lng) {
            const newLatLng: [number, number] = [newValue.lat, newValue.lng];

            if (marker.value) {
                marker.value.setLatLng(newLatLng);
            } else {
                marker.value = L.marker(newLatLng, {
                    draggable: !props.disabled,
                }).addTo(map.value);

                marker.value.bindPopup('Your Address');
                setupMarkerDragHandler(marker.value);
            }

            map.value.setView(newLatLng, 15);
        } else {
            if (marker.value) {
                map.value.removeLayer(marker.value);
                marker.value = null;
            }
        }
    },
    { deep: true }
);

onBeforeUnmount(() => {
    document.removeEventListener('click', handleClickOutside);
    
    if (searchTimeout) {
        clearTimeout(searchTimeout);
    }
    
    if (resizeObserver) {
        resizeObserver.disconnect();
        resizeObserver = null;
    }
    
    if (map.value) {
        map.value.remove();
        map.value = null;
    }
});
</script>

<template>
    <div class="space-y-4">
        <!-- Address Search -->
        <div ref="searchContainerRef" class="relative">
            <Label>Search Address</Label>
            <div class="relative">
                <Input
                    v-model="searchQuery"
                    type="text"
                    placeholder="Search for an address (e.g., Prk. 3-A, Southern Davao, Panabo City, Davao del Norte)"
                    class="pl-10 pr-10"
                    :disabled="disabled"
                    @input="handleSearchInput"
                    @keydown.enter.prevent="searchLocation(searchQuery)"
                />
                <Loader2
                    v-if="isSearching"
                    class="absolute left-3 top-1/2 transform -translate-y-1/2 h-4 w-4 text-muted-foreground animate-spin"
                />
            </div>

            <!-- Search Results Dropdown -->
            <div
                v-if="showResults && (searchResults.length > 0 || isSearching) && searchQuery"
                class="absolute z-50 mt-1 w-full rounded-md border bg-popover text-popover-foreground shadow-md max-h-[200px] overflow-auto"
            >
                <div
                    v-if="isSearching"
                    class="px-4 py-6 text-center text-sm text-muted-foreground"
                >
                    Searching...
                </div>
                <div
                    v-else-if="searchResults.length === 0 && searchQuery.trim()"
                    class="px-4 py-6 text-center text-sm text-muted-foreground"
                >
                    No locations found
                </div>
                <div
                    v-for="(result, index) in searchResults"
                    :key="index"
                    @click="selectLocation(result)"
                    class="cursor-pointer px-4 py-3 text-sm hover:bg-accent hover:text-accent-foreground border-b last:border-b-0"
                >
                    <div class="font-medium">{{ result.display_name }}</div>
                </div>
            </div>
        </div>

        <!-- Structured Address Fields -->
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div class="space-y-2">
                <Label for="province">Province <span class="text-red-500">*</span></Label>
                <Input
                    id="province"
                    :model-value="province"
                    @update:model-value="emit('update:province', $event)"
                    type="text"
                    required
                    :disabled="disabled"
                    placeholder="e.g., Davao del Norte"
                />
            </div>

            <div class="space-y-2">
                <Label for="city">City/Municipality <span class="text-red-500">*</span></Label>
                <Input
                    id="city"
                    :model-value="city"
                    @update:model-value="emit('update:city', $event)"
                    type="text"
                    required
                    :disabled="disabled"
                    placeholder="e.g., Panabo City"
                />
            </div>

            <div class="space-y-2">
                <Label for="barangay">Barangay <span class="text-red-500">*</span></Label>
                <Input
                    id="barangay"
                    :model-value="barangay"
                    @update:model-value="emit('update:barangay', $event)"
                    type="text"
                    required
                    :disabled="disabled"
                    placeholder="e.g., Southern Davao"
                />
            </div>

            <div class="space-y-2">
                <Label for="street">Street <span class="text-red-500">*</span></Label>
                <Input
                    id="street"
                    :model-value="street"
                    @update:model-value="emit('update:street', $event)"
                    type="text"
                    required
                    :disabled="disabled"
                    placeholder="e.g., Prk. 3-A"
                />
            </div>
        </div>

        <!-- Map Container -->
        <div class="relative">
            <Label>Location on Map</Label>
            <p class="text-xs text-muted-foreground mb-2">
                Click on the map to set a location pin, or drag the pin to adjust its position.
            </p>
            <div
                ref="mapContainer"
                class="w-full rounded-lg border"
                :style="{ height: height, zIndex: 1 }"
                :class="{ 'opacity-60': disabled }"
            ></div>
            <div
                v-if="disabled"
                class="absolute inset-0 rounded-lg cursor-not-allowed"
                :style="{ zIndex: 2 }"
            ></div>
        </div>
    </div>
</template>
