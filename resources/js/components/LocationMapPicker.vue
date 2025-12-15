<script setup lang="ts">
import { onMounted, ref, watch, onBeforeUnmount } from 'vue';
import L from 'leaflet';
import 'leaflet/dist/leaflet.css';

interface Props {
    modelValue?: { lat: number | null; lng: number | null } | null;
    height?: string;
}

const props = withDefaults(defineProps<Props>(), {
    modelValue: null,
    height: '400px',
});

const emit = defineEmits<{
    'update:modelValue': [value: { lat: number | null; lng: number | null }];
}>();

const mapContainer = ref<HTMLElement | null>(null);
const map = ref<L.Map | null>(null);
const marker = ref<L.Marker | null>(null);

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

onMounted(() => {
    if (!mapContainer.value) return;

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

    // Helper function to setup marker drag handler
    const setupMarkerDragHandler = (markerInstance: L.Marker) => {
        markerInstance.on('dragend', (e: L.DragEndEvent) => {
            const { lat, lng } = e.target.getLatLng();
            emit('update:modelValue', { lat, lng });
        });
    };

    // Add initial marker if coordinates exist
    if (props.modelValue?.lat && props.modelValue?.lng) {
        marker.value = L.marker([props.modelValue.lat, props.modelValue.lng], {
            draggable: true,
        }).addTo(map.value);

        marker.value.bindPopup('Location Pin<br/>Drag to adjust position');
        setupMarkerDragHandler(marker.value);
    }

    // Handle map click to add/move marker
    map.value.on('click', (e: L.LeafletMouseEvent) => {
        const { lat, lng } = e.latlng;

        if (marker.value) {
            marker.value.setLatLng([lat, lng]);
        } else {
            marker.value = L.marker([lat, lng], {
                draggable: true,
            }).addTo(map.value!);

            marker.value.bindPopup('Location Pin<br/>Drag to adjust position');
            setupMarkerDragHandler(marker.value);
        }

        emit('update:modelValue', { lat, lng });
    });
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
    if (map.value) {
        map.value.remove();
        map.value = null;
    }
});
</script>

<template>
    <div
        ref="mapContainer"
        class="w-full rounded-lg border"
        :style="{ height: height, zIndex: 1 }"
    ></div>
</template>


