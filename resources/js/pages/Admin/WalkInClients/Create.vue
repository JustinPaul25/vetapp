<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, Form, router } from '@inertiajs/vue3';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { SearchableSelect } from '@/components/ui/searchable-select';
import InputError from '@/components/InputError.vue';
import LocationMapPicker from '@/components/LocationMapPicker.vue';
import { UserPlus, ArrowLeft, Search, User, Dog, CheckCircle, Loader2 } from 'lucide-vue-next';
import { Link } from '@inertiajs/vue3';
import { dashboard } from '@/routes';
import { ref, computed, watch } from 'vue';
import axios from 'axios';

interface PetType {
    id: number;
    name: string;
}

interface FoundPet {
    id: number;
    pet_name: string;
    pet_breed: string;
    pet_gender: string | null;
    pet_birth_date: string | null;
    pet_allergies: string | null;
    pet_type: {
        id: number;
        name: string;
    };
    owner: {
        id: number;
        name: string;
        first_name: string | null;
        last_name: string | null;
        email: string;
        mobile_number: string | null;
        address: string | null;
        lat: number | null;
        lng: number | null;
    } | null;
}

interface AppointmentType {
    id: number;
    name: string;
}

interface Props {
    pet_types: PetType[];
    pet_breeds: Record<string, string[]>;
    appointment_types: AppointmentType[];
}

const props = defineProps<Props>();

const breadcrumbs = [
    { title: 'Dashboard', href: dashboard().url },
    { title: 'Walk-In Clients', href: '/admin/walk_in_clients' },
    { title: 'Create Walk-In Client', href: '#' },
];

const location = ref<{ lat: number | null; lng: number | null } | null>(null);
const currentStep = ref<'search' | 'form'>('search');
const searchPetName = ref('');
const foundPets = ref<FoundPet[]>([]);
const isSearching = ref(false);
const searchError = ref<string | null>(null);

// Email lookup state
const isLookingUpEmail = ref(false);
const emailLookupResult = ref<'found' | 'not_found' | null>(null);
const emailLookupTimeout = ref<ReturnType<typeof setTimeout> | null>(null);

// Track if existing pet was selected
const existingPetSelected = ref(false);

// Store existing pet/owner display info
const existingOwnerInfo = ref<{
    name: string;
    email: string;
    mobile_number: string | null;
    address: string | null;
} | null>(null);

const existingPetInfo = ref<{
    pet_name: string;
    pet_type: string;
    pet_breed: string;
    pet_gender: string | null;
    pet_birth_date: string | null;
    pet_allergies: string | null;
} | null>(null);

// Key to force LocationMapPicker re-initialization
const mapKey = ref(0);

const form = router.form({
    // Existing records (when using registered pet/owner)
    existing_owner_id: null as number | null,
    existing_pet_id: null as number | null,
    // Client fields (for new clients)
    first_name: '',
    last_name: '',
    name: '',
    email: '',
    mobile_number: '',
    address: '',
    lat: null as number | null,
    lng: null as number | null,
    // Pet fields (for new pets)
    pet_type_id: '',
    custom_pet_type_name: '', // For new custom pet types
    pet_name: '',
    pet_breed: '',
    custom_pet_breed_name: '', // For new custom breeds
    pet_gender: '',
    pet_birth_date: '',
    pet_allergies: '',
    // Appointment fields
    appointment_type_id: '',
    symptoms: '',
});

// Custom pet type display value (shown when creating new)
const customPetTypeDisplay = ref<string | null>(null);

// Custom pet breed display value (shown when creating new)
const customBreedDisplay = ref<string | null>(null);

// Handle creating a new pet type
const handleCreatePetType = (name: string) => {
    form.custom_pet_type_name = name;
    customPetTypeDisplay.value = name;
};

// Handle creating a new breed
const handleCreateBreed = (name: string) => {
    form.custom_pet_breed_name = name;
    customBreedDisplay.value = name;
};

// Transform pet types for SearchableSelect
const petTypeOptions = computed(() => {
    return props.pet_types.map(pt => ({
        value: pt.id.toString(),
        label: pt.name,
    }));
});

// Get the selected pet type name
const selectedPetTypeName = computed(() => {
    if (!form.pet_type_id) return null;
    const petType = props.pet_types.find(pt => pt.id.toString() === form.pet_type_id);
    return petType?.name || null;
});

// Get breed options based on selected pet type
const breedOptions = computed(() => {
    if (!selectedPetTypeName.value || !props.pet_breeds[selectedPetTypeName.value]) {
        return [];
    }
    return props.pet_breeds[selectedPetTypeName.value].map(breed => ({
        value: breed,
        label: breed,
    }));
});

// Clear breed selection when pet type changes
watch(() => form.pet_type_id, (newValue) => {
    form.pet_breed = '';
    form.custom_pet_breed_name = '';
    customBreedDisplay.value = null;
    // Clear custom pet type when selecting an existing type
    if (newValue !== '__new__') {
        form.custom_pet_type_name = '';
        customPetTypeDisplay.value = null;
    }
});

// Clear custom breed when selecting an existing breed
watch(() => form.pet_breed, (newValue) => {
    if (newValue !== '__new__') {
        form.custom_pet_breed_name = '';
        customBreedDisplay.value = null;
    }
});

// Email validation regex
const isValidEmail = (email: string) => {
    const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    return emailRegex.test(email);
};

// Lookup client by email with debounce
const lookupClientByEmail = async (email: string) => {
    if (!email || !isValidEmail(email)) {
        emailLookupResult.value = null;
        return;
    }

    isLookingUpEmail.value = true;
    emailLookupResult.value = null;

    try {
        const response = await axios.post('/admin/walk_in_clients/lookup-by-email', { email });
        
        if (response.data.found && response.data.client) {
            const client = response.data.client;
            
            // Autofill form fields
            form.first_name = client.first_name || '';
            form.last_name = client.last_name || '';
            form.name = client.name || '';
            form.mobile_number = client.mobile_number || '';
            form.address = client.address || '';
            
            // Update location if available
            if (client.lat && client.lng) {
                location.value = { lat: client.lat, lng: client.lng };
                form.lat = client.lat;
                form.lng = client.lng;
            }
            
            emailLookupResult.value = 'found';
        } else {
            emailLookupResult.value = 'not_found';
        }
    } catch (error) {
        console.error('Email lookup failed:', error);
        emailLookupResult.value = null;
    } finally {
        isLookingUpEmail.value = false;
    }
};

// Watch email field with debounce
watch(() => form.email, (newEmail) => {
    // Clear previous timeout
    if (emailLookupTimeout.value) {
        clearTimeout(emailLookupTimeout.value);
    }
    
    // Reset result when email changes
    emailLookupResult.value = null;
    
    // Set new debounced timeout (500ms delay)
    emailLookupTimeout.value = setTimeout(() => {
        lookupClientByEmail(newEmail);
    }, 500);
});

// Transform appointment types for SearchableSelect
const appointmentTypeOptions = computed(() => {
    return props.appointment_types.map(type => ({
        value: type.id.toString(),
        label: type.name,
    }));
});

const updateLocation = (value: { lat: number | null; lng: number | null }) => {
    location.value = value;
    form.lat = value.lat;
    form.lng = value.lng;
};

const searchPets = async () => {
    if (!searchPetName.value.trim()) {
        searchError.value = 'Please enter a pet name to search.';
        return;
    }

    isSearching.value = true;
    searchError.value = null;
    foundPets.value = [];

    try {
        const response = await axios.post('/admin/walk_in_clients/search-pets', {
            pet_name: searchPetName.value.trim(),
        });

        foundPets.value = response.data.pets;
        
        if (foundPets.value.length === 0) {
            searchError.value = 'No pets found with that name. You can proceed to create a new client and pet.';
        }
    } catch (error: any) {
        searchError.value = error.response?.data?.message || 'An error occurred while searching for pets.';
    } finally {
        isSearching.value = false;
    }
};

const useExistingPet = (pet: FoundPet) => {
    // Mark existing pet as selected
    existingPetSelected.value = true;
    
    // Store existing IDs for the backend
    form.existing_pet_id = pet.id;
    
    if (pet.owner) {
        form.existing_owner_id = pet.owner.id;
        
        // Store owner display info
        existingOwnerInfo.value = {
            name: pet.owner.name,
            email: pet.owner.email,
            mobile_number: pet.owner.mobile_number,
            address: pet.owner.address,
        };
    }
    
    // Store pet display info
    existingPetInfo.value = {
        pet_name: pet.pet_name,
        pet_type: pet.pet_type.name,
        pet_breed: pet.pet_breed,
        pet_gender: pet.pet_gender,
        pet_birth_date: pet.pet_birth_date,
        pet_allergies: pet.pet_allergies,
    };
    
    // Move to form step
    currentStep.value = 'form';
};

const continueToForm = () => {
    // Pre-fill pet name if searched
    if (searchPetName.value.trim()) {
        form.pet_name = searchPetName.value.trim();
    }
    currentStep.value = 'form';
};

const goBackToSearch = () => {
    currentStep.value = 'search';
};

const submit = () => {
    form.post('/admin/walk_in_clients');
};
</script>

<template>
    <Head title="Create Walk-In Client" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="container mx-auto p-6 max-w-4xl">
            <Card>
                <CardHeader>
                    <div class="flex items-center gap-2">
                        <Link href="/admin/walk_in_clients">
                            <Button variant="ghost" size="sm">
                                <ArrowLeft class="h-4 w-4" />
                            </Button>
                        </Link>
                        <div>
                            <CardTitle class="flex items-center gap-2">
                                <UserPlus class="h-5 w-5" />
                                Create New Walk-In Client
                            </CardTitle>
                            <CardDescription>
                                <span v-if="currentStep === 'search'">
                                    Step 1: Search for an existing pet by name. If found, you can use the existing owner information.
                                </span>
                                <span v-else>
                                    Step 2: Complete the client and pet information to register.
                                </span>
                            </CardDescription>
                        </div>
                    </div>
                </CardHeader>
                <CardContent>
                    <!-- Step 1: Search for Pet -->
                    <div v-if="currentStep === 'search'" class="space-y-6">
                        <div class="space-y-4">
                            <div class="space-y-2">
                                <Label for="search_pet_name">Search Pet by Name <span class="text-destructive">*</span></Label>
                                <div class="flex gap-2">
                                    <Input
                                        id="search_pet_name"
                                        v-model="searchPetName"
                                        type="text"
                                        placeholder="Enter pet name (e.g., Max, Bella)"
                                        autocomplete="off"
                                        @keyup.enter="searchPets"
                                        class="flex-1"
                                    />
                                    <Button 
                                        type="button" 
                                        @click="searchPets" 
                                        :disabled="isSearching || !searchPetName.trim()"
                                    >
                                        <Search class="h-4 w-4 mr-2" />
                                        {{ isSearching ? 'Searching...' : 'Search' }}
                                    </Button>
                                </div>
                                <p class="text-xs text-muted-foreground">
                                    Enter the pet's name to search the database. If found, you'll see the pet and owner information.
                                </p>
                                <InputError v-if="searchError" :message="searchError" />
                            </div>

                            <!-- Display Found Pets -->
                            <div v-if="foundPets.length > 0" class="space-y-4">
                                <div class="border-t pt-4">
                                    <h3 class="text-lg font-semibold mb-4">Found Pets</h3>
                                    <div class="space-y-3">
                                        <Card 
                                            v-for="pet in foundPets" 
                                            :key="pet.id"
                                            class="cursor-pointer hover:bg-accent transition-colors"
                                        >
                                            <CardContent class="p-4">
                                                <div class="flex items-start justify-between">
                                                    <div class="flex-1">
                                                        <div class="flex items-center gap-2 mb-2">
                                                            <Dog class="h-5 w-5 text-primary" />
                                                            <h4 class="font-semibold text-lg">{{ pet.pet_name }}</h4>
                                                            <span class="text-sm text-muted-foreground">({{ pet.pet_type.name }})</span>
                                                        </div>
                                                        <div class="space-y-1 text-sm text-muted-foreground">
                                                            <p><span class="font-medium">Breed:</span> {{ pet.pet_breed }}</p>
                                                            <p v-if="pet.pet_gender"><span class="font-medium">Gender:</span> {{ pet.pet_gender }}</p>
                                                        </div>
                                                        <div v-if="pet.owner" class="mt-3 pt-3 border-t">
                                                            <div class="flex items-center gap-2 mb-2">
                                                                <User class="h-4 w-4 text-primary" />
                                                                <h5 class="font-semibold">Owner Information</h5>
                                                            </div>
                                                            <div class="space-y-1 text-sm text-muted-foreground">
                                                                <p><span class="font-medium">Name:</span> {{ pet.owner.name }}</p>
                                                                <p><span class="font-medium">Email:</span> {{ pet.owner.email }}</p>
                                                                <p v-if="pet.owner.mobile_number"><span class="font-medium">Mobile:</span> {{ pet.owner.mobile_number }}</p>
                                                                <p v-if="pet.owner.address"><span class="font-medium">Address:</span> {{ pet.owner.address }}</p>
                                                            </div>
                                                        </div>
                                                        <div v-else class="mt-3 pt-3 border-t">
                                                            <p class="text-sm text-muted-foreground">No owner information available</p>
                                                        </div>
                                                    </div>
                                                    <Button 
                                                        v-if="pet.owner"
                                                        @click="useExistingPet(pet)"
                                                        class="ml-4"
                                                    >
                                                        Use This Pet & Owner
                                                    </Button>
                                                </div>
                                            </CardContent>
                                        </Card>
                                    </div>
                                </div>
                            </div>

                            <!-- Continue to Form Button -->
                            <div class="flex justify-end gap-4 pt-4 border-t">
                                <Link href="/admin/walk_in_clients">
                                    <Button type="button" variant="outline">Cancel</Button>
                                </Link>
                                <Button 
                                    type="button" 
                                    @click="continueToForm"
                                    :disabled="isSearching"
                                >
                                    Continue to Registration Form
                                </Button>
                            </div>
                        </div>
                    </div>

                    <!-- Step 2: Registration Form -->
                    <Form v-else @submit.prevent="submit" class="space-y-6">
                        <div class="flex items-center justify-between mb-4 pb-4 border-b">
                            <h3 class="text-lg font-semibold">Owner Information</h3>
                            <Button type="button" variant="ghost" size="sm" @click="goBackToSearch">
                                <ArrowLeft class="h-4 w-4 mr-2" />
                                Back to Search
                            </Button>
                        </div>

                        <!-- Existing Owner Display (Plain Text) -->
                        <div v-if="existingPetSelected && existingOwnerInfo" class="space-y-4">
                            <p class="text-xs text-green-600 flex items-center gap-1">
                                <CheckCircle class="h-3 w-3" />
                                Owner already registered in the system.
                            </p>
                            <div class="bg-muted/50 rounded-lg p-4 space-y-3">
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                    <div>
                                        <p class="text-sm font-medium text-muted-foreground">Name</p>
                                        <p class="text-sm">{{ existingOwnerInfo.name }}</p>
                                    </div>
                                    <div>
                                        <p class="text-sm font-medium text-muted-foreground">Email</p>
                                        <p class="text-sm">{{ existingOwnerInfo.email }}</p>
                                    </div>
                                    <div>
                                        <p class="text-sm font-medium text-muted-foreground">Mobile Number</p>
                                        <p class="text-sm">{{ existingOwnerInfo.mobile_number || 'Not provided' }}</p>
                                    </div>
                                    <div>
                                        <p class="text-sm font-medium text-muted-foreground">Address</p>
                                        <p class="text-sm">{{ existingOwnerInfo.address || 'Not provided' }}</p>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- New Owner Form (Inputs) -->
                        <template v-else>
                            <div class="space-y-2">
                                <Label for="email">Email <span class="text-red-500">*</span></Label>
                                <div class="relative">
                                    <Input
                                        id="email"
                                        v-model="form.email"
                                        type="email"
                                        required
                                        autocomplete="email"
                                        :class="{ 'pr-10': isLookingUpEmail || emailLookupResult }"
                                    />
                                    <div v-if="isLookingUpEmail" class="absolute right-3 top-1/2 -translate-y-1/2">
                                        <Loader2 class="h-4 w-4 animate-spin text-muted-foreground" />
                                    </div>
                                    <div v-else-if="emailLookupResult === 'found'" class="absolute right-3 top-1/2 -translate-y-1/2">
                                        <CheckCircle class="h-4 w-4 text-green-500" />
                                    </div>
                                </div>
                                <p v-if="emailLookupResult === 'found'" class="text-xs text-green-600 flex items-center gap-1">
                                    <CheckCircle class="h-3 w-3" />
                                    Client found! Form has been auto-filled with existing information.
                                </p>
                                <p v-else class="text-xs text-muted-foreground">
                                    Enter an email to check if the client is already registered.
                                </p>
                                <InputError :message="form.errors.email" />
                            </div>

                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <div class="space-y-2">
                                    <Label for="first_name">First Name</Label>
                                    <Input
                                        id="first_name"
                                        v-model="form.first_name"
                                        type="text"
                                        autocomplete="given-name"
                                        :disabled="emailLookupResult === 'found'"
                                    />
                                    <InputError :message="form.errors.first_name" />
                                </div>

                                <div class="space-y-2">
                                    <Label for="last_name">Last Name</Label>
                                    <Input
                                        id="last_name"
                                        v-model="form.last_name"
                                        type="text"
                                        autocomplete="family-name"
                                        :disabled="emailLookupResult === 'found'"
                                    />
                                    <InputError :message="form.errors.last_name" />
                                </div>
                            </div>

                            <div class="space-y-2">
                                <Label for="name">Full Name <span class="text-muted-foreground text-xs">(optional, auto-generated if left blank)</span></Label>
                                <Input
                                    id="name"
                                    v-model="form.name"
                                    type="text"
                                    autocomplete="name"
                                    placeholder="Will be auto-generated from first and last name"
                                    :disabled="emailLookupResult === 'found'"
                                />
                                <InputError :message="form.errors.name" />
                            </div>

                            <div class="space-y-2">
                                <Label for="mobile_number">Mobile Number <span class="text-muted-foreground text-xs">(Philippine number)</span></Label>
                                <Input
                                    id="mobile_number"
                                    v-model="form.mobile_number"
                                    type="tel"
                                    autocomplete="tel"
                                    placeholder="09123456789 or +639123456789"
                                    :disabled="emailLookupResult === 'found'"
                                />
                                <p class="text-xs text-muted-foreground">Format: 09XX XXX XXXX or +639XX XXX XXXX</p>
                                <InputError :message="form.errors.mobile_number" />
                            </div>

                            <div class="space-y-2">
                                <Label for="address">Complete Address</Label>
                                <Input
                                    id="address"
                                    v-model="form.address"
                                    type="text"
                                    autocomplete="street-address"
                                    placeholder="Street, Barangay, City, Province"
                                    :disabled="emailLookupResult === 'found'"
                                />
                                <InputError :message="form.errors.address" />
                            </div>

                            <div v-if="emailLookupResult !== 'found'" class="space-y-2">
                                <Label>Location Pin</Label>
                                <p class="text-xs text-muted-foreground mb-2">Click on the map to set a location pin, or drag the pin to adjust its position.</p>
                                <LocationMapPicker
                                    :model-value="location"
                                    @update:model-value="updateLocation"
                                    height="400px"
                                />
                                <InputError :message="form.errors.lat" />
                                <InputError :message="form.errors.lng" />
                            </div>
                        </template>

                        <!-- Pet Information Section -->
                        <div class="border-t pt-6 mt-6">
                            <h3 class="text-lg font-semibold mb-4">Pet Information</h3>
                            
                            <!-- Existing Pet Display (Plain Text) -->
                            <div v-if="existingPetSelected && existingPetInfo" class="space-y-4">
                                <p class="text-xs text-green-600 flex items-center gap-1">
                                    <CheckCircle class="h-3 w-3" />
                                    Pet already registered in the system.
                                </p>
                                <div class="bg-muted/50 rounded-lg p-4 space-y-3">
                                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                        <div>
                                            <p class="text-sm font-medium text-muted-foreground">Pet Name</p>
                                            <p class="text-sm">{{ existingPetInfo.pet_name || 'Not provided' }}</p>
                                        </div>
                                        <div>
                                            <p class="text-sm font-medium text-muted-foreground">Pet Type</p>
                                            <p class="text-sm">{{ existingPetInfo.pet_type }}</p>
                                        </div>
                                        <div>
                                            <p class="text-sm font-medium text-muted-foreground">Breed</p>
                                            <p class="text-sm">{{ existingPetInfo.pet_breed }}</p>
                                        </div>
                                        <div>
                                            <p class="text-sm font-medium text-muted-foreground">Gender</p>
                                            <p class="text-sm">{{ existingPetInfo.pet_gender || 'Not provided' }}</p>
                                        </div>
                                        <div>
                                            <p class="text-sm font-medium text-muted-foreground">Birth Date</p>
                                            <p class="text-sm">{{ existingPetInfo.pet_birth_date || 'Not provided' }}</p>
                                        </div>
                                    </div>
                                    <div v-if="existingPetInfo.pet_allergies">
                                        <p class="text-sm font-medium text-muted-foreground">Allergies</p>
                                        <p class="text-sm">{{ existingPetInfo.pet_allergies }}</p>
                                    </div>
                                </div>
                            </div>

                            <!-- New Pet Form (Inputs) -->
                            <template v-else>
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                    <div class="space-y-2">
                                        <Label for="pet_type_id">Pet Type <span class="text-destructive">*</span></Label>
                                        <SearchableSelect
                                            id="pet_type_id"
                                            v-model="form.pet_type_id"
                                            :options="petTypeOptions"
                                            placeholder="Select Pet Type"
                                            search-placeholder="Search pet types..."
                                            :required="true"
                                            :allow-create="true"
                                            create-prefix="Add new type"
                                            :custom-value="customPetTypeDisplay"
                                            @update:custom-value="(val) => customPetTypeDisplay = val"
                                            @create="handleCreatePetType"
                                        />
                                        <p v-if="customPetTypeDisplay" class="text-xs text-blue-600">
                                            New pet type "{{ customPetTypeDisplay }}" will be created when you submit the form.
                                        </p>
                                        <InputError :message="form.errors.pet_type_id" />
                                        <InputError :message="form.errors.custom_pet_type_name" />
                                    </div>

                                    <div class="space-y-2">
                                        <Label for="pet_name">Pet Name</Label>
                                        <Input
                                            id="pet_name"
                                            v-model="form.pet_name"
                                            type="text"
                                            placeholder="e.g., Max, Bella"
                                            autocomplete="off"
                                        />
                                        <InputError :message="form.errors.pet_name" />
                                    </div>

                                    <div class="space-y-2">
                                        <Label for="pet_breed">Pet Breed <span class="text-destructive">*</span></Label>
                                        <SearchableSelect
                                            id="pet_breed"
                                            v-model="form.pet_breed"
                                            :options="breedOptions"
                                            placeholder="Select Pet Breed"
                                            search-placeholder="Search breeds..."
                                            :required="true"
                                            :disabled="!selectedPetTypeName && !customPetTypeDisplay"
                                            :allow-create="!!selectedPetTypeName || !!customPetTypeDisplay"
                                            create-prefix="Add new breed"
                                            :custom-value="customBreedDisplay"
                                            @update:custom-value="(val) => customBreedDisplay = val"
                                            @create="handleCreateBreed"
                                        />
                                        <p v-if="customBreedDisplay" class="text-xs text-blue-600">
                                            New breed "{{ customBreedDisplay }}" will be created when you submit the form.
                                        </p>
                                        <InputError :message="form.errors.pet_breed" />
                                        <InputError :message="form.errors.custom_pet_breed_name" />
                                    </div>

                                    <div class="space-y-2">
                                        <Label for="pet_gender">Pet Gender</Label>
                                        <select
                                            id="pet_gender"
                                            v-model="form.pet_gender"
                                            class="flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50"
                                        >
                                            <option value="">Select Gender</option>
                                            <option value="Male">Male</option>
                                            <option value="Female">Female</option>
                                        </select>
                                        <InputError :message="form.errors.pet_gender" />
                                    </div>

                                    <div class="space-y-2">
                                        <Label for="pet_birth_date">Pet Birth Date</Label>
                                        <Input
                                            id="pet_birth_date"
                                            v-model="form.pet_birth_date"
                                            type="date"
                                            autocomplete="off"
                                        />
                                        <InputError :message="form.errors.pet_birth_date" />
                                    </div>
                                </div>

                                <div class="space-y-2 mt-6">
                                    <Label for="pet_allergies">Pet Allergies</Label>
                                    <Input
                                        id="pet_allergies"
                                        v-model="form.pet_allergies"
                                        type="text"
                                        placeholder="e.g., Peanuts, Pollen"
                                        autocomplete="off"
                                    />
                                    <InputError :message="form.errors.pet_allergies" />
                                </div>
                            </template>
                        </div>

                        <!-- Appointment Section -->
                        <div class="border-t pt-6 mt-6">
                            <h3 class="text-lg font-semibold mb-4">Visit Details</h3>
                            <div class="space-y-4">
                                <div class="space-y-2">
                                    <Label for="appointment_type_id">Type of Services <span class="text-destructive">*</span></Label>
                                    <SearchableSelect
                                        id="appointment_type_id"
                                        v-model="form.appointment_type_id"
                                        :options="appointmentTypeOptions"
                                        placeholder="Select Type of Services"
                                        search-placeholder="Search appointment types..."
                                        :required="true"
                                    />
                                    <InputError :message="form.errors.appointment_type_id" />
                                </div>

                                <div class="space-y-2">
                                    <Label for="symptoms">Symptoms / Notes</Label>
                                    <textarea
                                        id="symptoms"
                                        v-model="form.symptoms"
                                        rows="3"
                                        class="flex min-h-[80px] w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50"
                                        placeholder="Describe any symptoms or notes for this appointment..."
                                    ></textarea>
                                    <InputError :message="form.errors.symptoms" />
                                </div>
                            </div>
                        </div>

                        <div class="flex justify-end gap-4">
                            <Link href="/admin/walk_in_clients">
                                <Button type="button" variant="outline">Cancel</Button>
                            </Link>
                            <Button type="submit" :disabled="form.processing">
                                Approve
                            </Button>
                        </div>
                    </Form>
                </CardContent>
            </Card>
        </div>
    </AppLayout>
</template>

