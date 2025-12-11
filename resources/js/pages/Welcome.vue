<script setup lang="ts">
    import { dashboard, login, home } from '@/routes';
    import { Head, Link } from '@inertiajs/vue3';
    import { ref } from 'vue';
    
    withDefaults(
        defineProps<{
            canRegister: boolean;
        }>(),
        {
            canRegister: true,
        },
    );
    
    const searchInput = ref('');
    const searchResults = ref<any[]>([]);
    const isSearching = ref(false);
    const showResults = ref(false);
    const mobileMenuOpen = ref(false);
    
    const handleSearch = async (e: Event) => {
        e.preventDefault();
        if (!searchInput.value.trim()) return;
    
        isSearching.value = true;
        showResults.value = true;
        searchResults.value = [];
    
        try {
            const response = await fetch(`/search-diseases?keyword=${encodeURIComponent(searchInput.value)}`, {
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
            });
            if (response.ok) {
                const data = await response.json();
                // Handle both array and object responses
                if (Array.isArray(data)) {
                    searchResults.value = data;
                } else if (data && typeof data === 'object') {
                    searchResults.value = Object.values(data);
                } else {
                    searchResults.value = [];
                }
            } else {
                searchResults.value = [];
            }
        } catch (error) {
            console.error('Search error:', error);
            searchResults.value = [];
        } finally {
            isSearching.value = false;
        }
    };
    
    const scrollToSection = (id: string) => {
        const element = document.getElementById(id);
        if (element) {
            element.scrollIntoView({ behavior: 'smooth' });
        }
    };
    </script>
    
    <template>
        <Head title="Welcome to Panabo City ANIMED">
            <link rel="preconnect" href="https://rsms.me/" />
            <link rel="stylesheet" href="https://rsms.me/inter/inter.css" />
        </Head>
    
        <div class="min-h-screen bg-white">
            <!-- Navigation Header -->
            <nav class="bg-blue-600 text-white shadow-lg">
                <div class="container mx-auto px-4">
                    <div class="flex items-center justify-between h-16">
                        <Link :href="home()" class="flex items-center space-x-3">
                            <img src="/media/logo.png" alt="Panabo City ANIMED" class="h-12" />
                            <span class="font-bold text-lg">Panabo City ANIMED</span>
                        </Link>
                        <div class="hidden md:flex items-center space-x-6">
                            <button @click="scrollToSection('home')" class="hover:text-blue-200 transition">Home</button>
                            <button @click="scrollToSection('features')" class="hover:text-blue-200 transition">Features</button>
                            <button @click="scrollToSection('search-disease')" class="hover:text-blue-200 transition">Search Disease</button>
                            <button @click="scrollToSection('services')" class="hover:text-blue-200 transition">Services</button>
                            <button @click="scrollToSection('about')" class="hover:text-blue-200 transition">About</button>
                            <template v-if="$page.props.auth.user">
                                <Link :href="dashboard()" class="login-button bg-white px-4 py-2 rounded font-semibold hover:bg-blue-50 transition">
                                    Dashboard
                                </Link>
                            </template>
                            <template v-else>
                                <Link :href="login()" class="login-button bg-white px-4 py-2 rounded font-semibold hover:bg-blue-50 transition">
                                    Log In
                                </Link>
                            </template>
                        </div>
                        <!-- Mobile menu button -->
                        <button class="md:hidden" @click="mobileMenuOpen = !mobileMenuOpen">
                            <svg v-if="!mobileMenuOpen" class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
                            </svg>
                            <svg v-else class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                            </svg>
                        </button>
                    </div>
                </div>
                <!-- Mobile menu -->
                <div v-if="mobileMenuOpen" class="md:hidden bg-blue-700">
                    <div class="px-4 pt-2 pb-4 space-y-2">
                        <button @click="scrollToSection('home'); mobileMenuOpen = false" class="block w-full text-left px-4 py-2 hover:bg-blue-600 rounded">Home</button>
                        <button @click="scrollToSection('features'); mobileMenuOpen = false" class="block w-full text-left px-4 py-2 hover:bg-blue-600 rounded">Features</button>
                        <button @click="scrollToSection('search-disease'); mobileMenuOpen = false" class="block w-full text-left px-4 py-2 hover:bg-blue-600 rounded">Search Disease</button>
                        <button @click="scrollToSection('services'); mobileMenuOpen = false" class="block w-full text-left px-4 py-2 hover:bg-blue-600 rounded">Services</button>
                        <button @click="scrollToSection('about'); mobileMenuOpen = false" class="block w-full text-left px-4 py-2 hover:bg-blue-600 rounded">About</button>
                        <div class="pt-2 border-t border-blue-500">
                            <template v-if="$page.props.auth.user">
                                <Link :href="dashboard()" class="login-button block bg-white px-4 py-2 rounded font-semibold text-center hover:bg-blue-50 transition">
                                    Dashboard
                                </Link>
                            </template>
                            <template v-else>
                                <Link :href="login()" class="login-button block bg-white px-4 py-2 rounded font-semibold text-center hover:bg-blue-50 transition">Log In</Link>
                            </template>
                        </div>
                    </div>
                </div>
            </nav>
    
            <!-- Hero Section -->
            <section id="home" class="hero-section flex items-center justify-center text-center text-white min-h-[90vh] relative">
                <div class="absolute inset-0 bg-gradient-to-b from-blue-900/80 via-blue-800/60 to-black/80"></div>
                <div class="container mx-auto px-4 relative z-10">
                    <img src="/media/logo.png" alt="Panabo City ANIMED" class="h-64 mx-auto mb-6" />
                    <h2 class="text-5xl md:text-6xl font-bold mb-4">Welcome to Panabo City ANIMED</h2>
                    <p class="text-xl md:text-2xl mb-8">Trusted veterinary care for your beloved pets.</p>
                    <Link
                        v-if="$page.props.auth.user"
                        :href="dashboard()"
                        class="inline-block bg-blue-600 hover:bg-blue-700 text-white font-bold py-3 px-8 rounded-lg text-lg transition"
                    >
                        Book an Appointment
                    </Link>
                    <Link
                        v-else
                        :href="login()"
                        class="inline-block bg-blue-600 hover:bg-blue-700 text-white font-bold py-3 px-8 rounded-lg text-lg transition"
                    >
                        Book an Appointment
                    </Link>
                </div>
            </section>
    
            <!-- Features Section -->
            <section id="features" class="py-16 bg-white">
                <div class="container mx-auto px-4">
                    <h2 class="text-3xl md:text-4xl font-bold text-center mb-4">App Features</h2>
                    <p class="text-center text-gray-600 mb-12 max-w-2xl mx-auto">
                        Discover the powerful features that make managing your pet's health easier than ever.
                    </p>
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8">
                        <div class="bg-white rounded-lg shadow-lg p-6 hover:shadow-xl transition-shadow border border-gray-100">
                            <div class="bg-blue-100 w-16 h-16 rounded-full flex items-center justify-center mb-4 mx-auto">
                                <svg class="w-8 h-8 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                </svg>
                            </div>
                            <h3 class="text-xl font-semibold text-center mb-3">Easy Appointments</h3>
                            <p class="text-gray-600 text-center">
                                Schedule and manage veterinary appointments online with just a few clicks. View your appointment history and upcoming visits all in one place.
                            </p>
                        </div>
                        <div class="bg-white rounded-lg shadow-lg p-6 hover:shadow-xl transition-shadow border border-gray-100">
                            <div class="bg-pink-100 w-16 h-16 rounded-full flex items-center justify-center mb-4 mx-auto">
                                <svg class="w-8 h-8 text-pink-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z" />
                                </svg>
                            </div>
                            <h3 class="text-xl font-semibold text-center mb-3">Pet Management</h3>
                            <p class="text-gray-600 text-center">
                                Keep track of all your pets in one convenient location. Store medical records, vaccination history, and important health information.
                            </p>
                        </div>
                        <div class="bg-white rounded-lg shadow-lg p-6 hover:shadow-xl transition-shadow border border-gray-100">
                            <div class="bg-green-100 w-16 h-16 rounded-full flex items-center justify-center mb-4 mx-auto">
                                <svg class="w-8 h-8 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                                </svg>
                            </div>
                            <h3 class="text-xl font-semibold text-center mb-3">Disease Search</h3>
                            <p class="text-gray-600 text-center">
                                Quickly search for diseases by symptoms to get instant information about potential conditions and recommended home remedies.
                            </p>
                        </div>
                        <div class="bg-white rounded-lg shadow-lg p-6 hover:shadow-xl transition-shadow border border-gray-100">
                            <div class="bg-teal-100 w-16 h-16 rounded-full flex items-center justify-center mb-4 mx-auto">
                                <svg class="w-8 h-8 text-teal-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                </svg>
                            </div>
                            <h3 class="text-xl font-semibold text-center mb-3">Digital Prescriptions</h3>
                            <p class="text-gray-600 text-center">
                                Access your pet's prescriptions online. View medication history and get reminders for refills and follow-up appointments.
                            </p>
                        </div>
                        <div class="bg-white rounded-lg shadow-lg p-6 hover:shadow-xl transition-shadow border border-gray-100">
                            <div class="bg-orange-100 w-16 h-16 rounded-full flex items-center justify-center mb-4 mx-auto">
                                <svg class="w-8 h-8 text-orange-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19.428 15.428a2 2 0 00-1.022-.547l-2.387-.477a6 6 0 00-3.86.517l-.318.158a6 6 0 01-3.86.517L6.05 15.21a2 2 0 00-1.806.547M8 4h8l-1 1v5.172a2 2 0 00.586 1.414l5 5c1.26 1.26.367 3.414-1.415 3.414H4.828c-1.782 0-2.674-2.154-1.414-3.414l5-5A2 2 0 009 10.172V5L8 4z" />
                                </svg>
                            </div>
                            <h3 class="text-xl font-semibold text-center mb-3">Medicine Tracking</h3>
                            <p class="text-gray-600 text-center">
                                Keep track of medications and treatments. Get information about available medicines and their proper usage.
                            </p>
                        </div>
                        <div class="bg-white rounded-lg shadow-lg p-6 hover:shadow-xl transition-shadow border border-gray-100">
                            <div class="bg-purple-100 w-16 h-16 rounded-full flex items-center justify-center mb-4 mx-auto">
                                <svg class="w-8 h-8 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
                                </svg>
                            </div>
                            <h3 class="text-xl font-semibold text-center mb-3">Secure & Reliable</h3>
                            <p class="text-gray-600 text-center">
                                Your pet's health data is protected with secure encryption. Access your information anytime, anywhere with peace of mind.
                            </p>
                        </div>
                    </div>
                </div>
            </section>
    
            <!-- Disease Search Section -->
            <section id="search-disease" class="py-12 bg-gray-50">
                <div class="container mx-auto px-4 py-12">
                    <h2 class="text-3xl md:text-4xl font-bold text-center mb-8">Search Disease by Symptoms</h2>
                    <form @submit="handleSearch" class="max-w-2xl mx-auto">
                        <div class="flex gap-2">
                            <input
                                v-model="searchInput"
                                type="text"
                                placeholder="Enter symptom (e.g. vomiting, itching, etc.)"
                                class="flex-1 px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
                            />
                            <button
                                type="submit"
                                :disabled="isSearching"
                                class="bg-blue-600 hover:bg-blue-700 text-white font-semibold px-6 py-3 rounded-lg transition disabled:opacity-50"
                            >
                                <span v-if="!isSearching">Search</span>
                                <span v-else class="flex items-center">
                                    <svg class="animate-spin -ml-1 mr-2 h-4 w-4 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                    </svg>
                                    Searching...
                                </span>
                            </button>
                        </div>
                    </form>
    
                    <div v-if="showResults" class="max-w-4xl mx-auto mt-8">
                        <div v-if="isSearching" class="text-center py-8">
                            <p class="text-gray-600">Searching...</p>
                        </div>
                        <div v-else-if="searchResults.length > 0" class="space-y-4">
                            <div
                                v-for="(disease, index) in searchResults"
                                :key="index"
                                class="bg-white rounded-lg shadow-md p-6"
                            >
                                <h5 class="text-xl font-semibold mb-3">{{ disease.name }}</h5>
                                <p v-if="disease.symptoms" class="mb-2">
                                    <strong>Common symptoms:</strong> <span v-html="disease.symptoms"></span>
                                </p>
                                <p v-if="disease.home_remedy" class="mb-0">
                                    <strong>Suggested home remedy:</strong> {{ disease.home_remedy }}
                                </p>
                            </div>
                            <div class="text-center mt-6">
                                <Link
                                    v-if="$page.props.auth.user"
                                    :href="dashboard()"
                                    class="inline-block bg-blue-600 hover:bg-blue-700 text-white font-bold py-3 px-8 rounded-lg text-lg transition"
                                >
                                    Book an Appointment
                                </Link>
                                <Link
                                    v-else
                                    :href="login()"
                                    class="inline-block bg-blue-600 hover:bg-blue-700 text-white font-bold py-3 px-8 rounded-lg text-lg transition"
                                >
                                    Book an Appointment
                                </Link>
                            </div>
                        </div>
                        <div v-else class="text-center py-8">
                            <p class="text-gray-600">No disease match found.</p>
                        </div>
                    </div>
                </div>
            </section>
    
            <!-- Services Section -->
            <section id="services" class="py-12">
                <div class="container mx-auto px-4 text-center">
                    <h2 class="text-3xl md:text-4xl font-bold mb-12">Our Services</h2>
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
                        <div class="bg-white rounded-lg shadow-lg p-6 hover:shadow-xl transition-shadow">
                            <img src="/media/icons/consultation.png" alt="Consultation" class="h-32 mx-auto mb-4" />
                            <h5 class="text-xl font-semibold mb-3">Online Consultations</h5>
                            <p class="text-gray-600">
                                Get expert veterinary advice from the comfort of your home, with personalised support tailored to your pet's needs.
                            </p>
                        </div>
                        <div class="bg-white rounded-lg shadow-lg p-6 hover:shadow-xl transition-shadow">
                            <img src="/media/icons/dog.png" alt="Products" class="h-32 mx-auto mb-4" />
                            <h5 class="text-xl font-semibold mb-3">Over-the-counter product suggestions</h5>
                            <p class="text-gray-600">
                                Online prescriptions are available, and our vets can also recommend non-medicated products that may help address your pet's health concerns.
                            </p>
                        </div>
                        <div class="bg-white rounded-lg shadow-lg p-6 hover:shadow-xl transition-shadow">
                            <img src="/media/icons/care.png" alt="Care" class="h-32 mx-auto mb-4" />
                            <h5 class="text-xl font-semibold mb-3">Care and Support</h5>
                            <p class="text-gray-600">
                                Our vets collaborate with you to understand your pet's health and wellness needs, offering expert guidance and clear next steps to support your pet's wellbeing.
                            </p>
                        </div>
                    </div>
                </div>
            </section>
    
            <!-- About Section -->
            <section id="about" class="py-12 bg-gray-50">
                <div class="container mx-auto px-4 text-center">
                    <h2 class="text-3xl md:text-4xl font-bold mb-8">About Us</h2>
                    <p class="max-w-4xl mx-auto text-lg text-gray-700 leading-relaxed">
                        At ANIMED Panabo City, we are committed to delivering the highest quality veterinary care with compassion and expertise. Our passionate team of experienced veterinarians and dedicated staff are here to support you and your beloved pets at every stage of their journey — from preventive health and wellness to advanced medical treatments. We believe that every animal deserves personalized, gentle care, and we are honored to be your trusted partners in ensuring the health, happiness, and well-being of your furry family members.
                    </p>
                </div>
            </section>
    
            <!-- Footer -->
            <footer class="bg-blue-600 text-white py-6">
                <div class="container mx-auto px-4 text-center">
                    <small>&copy; 2025 Panabo City ANIMED. All rights reserved.</small>
                </div>
            </footer>
        </div>
    </template>
    
    <style scoped>
    .hero-section {
        background-image: url('/media/background.jpg');
        background-size: cover;
        background-position: center;
        background-repeat: no-repeat;
        color: #ffffff !important;
    }
    
    .hero-section h2,
    .hero-section p,
    .hero-section span {
        color: #ffffff !important;
    }
    
    .hero-section * {
        color: #ffffff !important;
    }
    
    /* Override any dark mode text color inheritance */
    :deep(.hero-section),
    :deep(.hero-section h2),
    :deep(.hero-section p),
    :deep(.hero-section span),
    :deep(.hero-section *) {
        color: #ffffff !important;
    }
    
    /* Ensure navigation text stays white */
    nav.text-white,
    nav.text-white *,
    nav span {
        color: #ffffff !important;
    }
    
    /* Ensure footer text stays white */
    footer.text-white,
    footer.text-white *,
    footer small {
        color: #ffffff !important;
    }
    
    /* Force text colors in other sections to be visible */
    #search-disease h2,
    #services h2,
    #about h2,
    #features h2 {
        color: #1f2937 !important;
    }
    
    #search-disease p.text-gray-600,
    #services p.text-gray-600,
    #about p.text-gray-700,
    #features p.text-gray-600 {
        color: #4b5563 !important;
    }
    
    #services h5,
    #search-disease h5,
    #features h3 {
        color: #111827 !important;
    }
    
    /* Ensure login button text is visible */
    .login-button,
    .login-button * {
        color: #2563eb !important;
    }
    
    nav .login-button {
        color: #2563eb !important;
    }
    
    nav .login-button:hover {
        background-color: #eff6ff !important;
    }
    </style>
    