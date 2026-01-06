<script setup lang="ts">
import { onMounted, ref, watch, onBeforeUnmount } from 'vue';
import L from 'leaflet';
import 'leaflet/dist/leaflet.css';
import { Input } from '@/components/ui/input';
import { Search, Loader2 } from 'lucide-vue-next';
import { cn } from '@/lib/utils';

interface Props {
    modelValue?: { lat: number | null; lng: number | null } | null;
    height?: string;
    disabled?: boolean;
}

const props = withDefaults(defineProps<Props>(), {
    modelValue: null,
    height: '400px',
    disabled: false,
});

const emit = defineEmits<{
    'update:modelValue': [value: { lat: number | null; lng: number | null }];
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

// Geocoding interface
interface GeocodeResult {
    display_name: string;
    lat: string;
    lon: string;
}

// Search for locations using Nominatim
const searchLocation = async () => {
    if (!searchQuery.value.trim()) {
        searchResults.value = [];
        showResults.value = false;
        return;
    }

    isSearching.value = true;
    showResults.value = true;

    try {
        // Use backend proxy to avoid CORS issues
        const response = await fetch(
            `/api/geocode/search?q=${encodeURIComponent(searchQuery.value)}&limit=5`,
            {
                method: 'GET',
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
            }
        );

        if (response.ok) {
            const data = await response.json();
            if (Array.isArray(data)) {
                searchResults.value = data;
            } else {
                searchResults.value = [];
            }
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

// Debounced search function
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
        searchLocation();
    }, 500); // Wait 500ms after user stops typing
};

// Select a location from search results
const selectLocation = (result: GeocodeResult) => {
    const lat = parseFloat(result.lat);
    const lng = parseFloat(result.lon);

    if (!isNaN(lat) && !isNaN(lng)) {
        // Update marker position
        const newLatLng: [number, number] = [lat, lng];

        if (marker.value) {
            marker.value.setLatLng(newLatLng);
        } else if (map.value) {
            marker.value = L.marker(newLatLng, {
                draggable: true,
            }).addTo(map.value);

            marker.value.bindPopup('Location Pin<br/>Drag to adjust position');
            setupMarkerDragHandler(marker.value);
        }

        // Update map view
        if (map.value) {
            map.value.setView(newLatLng, 15);
        }

        // Emit the update
        emit('update:modelValue', { lat, lng });

        // Clear search
        searchQuery.value = '';
        searchResults.value = [];
        showResults.value = false;
    }
};

// Handle click outside to close search results
const handleClickOutside = (event: MouseEvent) => {
    if (searchContainerRef.value && !searchContainerRef.value.contains(event.target as Node)) {
        showResults.value = false;
    }
};

// Helper function to setup marker drag handler (moved up for reuse)
const setupMarkerDragHandler = (markerInstance: L.Marker) => {
    markerInstance.on('dragend', (e: L.DragEndEvent) => {
        const { lat, lng } = e.target.getLatLng();
        emit('update:modelValue', { lat, lng });
    });
};

// Initialize the map
const initializeMap = () => {
    if (!mapContainer.value || map.value) return;

    // Initialize map
    const initialCenter: [number, number] = props.modelValue?.lat && props.modelValue?.lng
        ? [props.modelValue.lat, props.modelValue.lng]
        : defaultCenter;

    map.value = L.map(mapContainer.value).setView(initialCenter, defaultZoom);

    // Add OpenStreetMap tile layer
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '© OpenStreetMap contributors',
        maxZoom: 19,
    }).addTo(map.value);

    // Add initial marker if coordinates exist
    if (props.modelValue?.lat && props.modelValue?.lng) {
        marker.value = L.marker([props.modelValue.lat, props.modelValue.lng], {
            draggable: !props.disabled,
        }).addTo(map.value);

        marker.value.bindPopup('Location Pin<br/>Drag to adjust position');
        setupMarkerDragHandler(marker.value);
    }

    // Handle map click to add/move marker
    map.value.on('click', (e: L.LeafletMouseEvent) => {
        // Don't allow map interaction when disabled
        if (props.disabled) return;
        
        const { lat, lng } = e.latlng;

        if (marker.value) {
            marker.value.setLatLng([lat, lng]);
        } else {
            marker.value = L.marker([lat, lng], {
                draggable: !props.disabled,
            }).addTo(map.value!);

            marker.value.bindPopup('Location Pin<br/>Drag to adjust position');
            setupMarkerDragHandler(marker.value);
        }

        emit('update:modelValue', { lat, lng });
    });
};

// ResizeObserver to detect when the container becomes visible
let resizeObserver: ResizeObserver | null = null;

onMounted(() => {
    document.addEventListener('click', handleClickOutside);
    
    if (!mapContainer.value) return;

    // Use ResizeObserver to detect when container becomes visible/resized
    resizeObserver = new ResizeObserver((entries) => {
        for (const entry of entries) {
            if (entry.contentRect.width > 0 && entry.contentRect.height > 0) {
                if (!map.value) {
                    // Initialize map when container becomes visible
                    initializeMap();
                } else {
                    // Invalidate size when container is resized
                    map.value.invalidateSize();
                }
            }
        }
    });
    
    resizeObserver.observe(mapContainer.value);
    
    // Try to initialize immediately if container is already visible
    initializeMap();
});

// Watch for external changes to modelValue
watch(
    () => props.modelValue,
    (newValue) => {
        if (!map.value) return;

        if (newValue?.lat && newValue?.lng) {
            const newLatLng: [number, number] = [newValue.lat, newValue.lng];

            if (marker.value) {
                marker.value.setLatLng(newLatLng);
            } else {
                marker.value = L.marker(newLatLng, {
                    draggable: true,
                }).addTo(map.value);

                marker.value.bindPopup('Location Pin<br/>Drag to adjust position');
                setupMarkerDragHandler(marker.value);
            }

            map.value.setView(newLatLng, defaultZoom);
        } else {
            // Remove marker if coordinates are cleared
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
    <div class="space-y-2">
        <!-- Search Input -->
        <div ref="searchContainerRef" class="relative">
            <div class="relative">
                <Search class="absolute left-3 top-1/2 transform -translate-y-1/2 h-4 w-4 text-muted-foreground" />
                <Input
                    v-model="searchQuery"
                    type="text"
                    placeholder="Search for a location (e.g., Manila, Quezon City, Makati)"
                    class="pl-10 pr-10"
                    :disabled="disabled"
                    @input="handleSearchInput"
                    @keydown.enter.prevent="searchLocation"
                />
                <Loader2
                    v-if="isSearching"
                    class="absolute right-3 top-1/2 transform -translate-y-1/2 h-4 w-4 text-muted-foreground animate-spin"
                />
            </div>

            <!-- Search Results Dropdown -->
            <div
                v-if="showResults && (searchResults.length > 0 || searchQuery.trim())"
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
                    :class="cn(
                        'cursor-pointer px-4 py-3 text-sm hover:bg-accent hover:text-accent-foreground',
                        'border-b last:border-b-0'
                    )"
                >
                    <div class="font-medium">{{ result.display_name }}</div>
                </div>
            </div>
        </div>

        <!-- Map Container -->
        <div class="relative">
            <div
                ref="mapContainer"
                class="w-full rounded-lg border"
                :style="{ height: height, zIndex: 1 }"
                :class="{ 'opacity-60': disabled }"
            ></div>
            <!-- Disabled overlay to prevent map interaction -->
            <div
                v-if="disabled"
                class="absolute inset-0 rounded-lg cursor-not-allowed"
                :style="{ zIndex: 2 }"
            ></div>
        </div>
    </div>
</template>












