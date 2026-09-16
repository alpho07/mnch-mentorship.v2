{{-- resources/views/filament/pages/training-export.blade.php --}}
<x-filament-panels::page>
    <div class="space-y-6">
        {{-- Export Status/Progress Bar Section --}}
        <div id="export-progress" class="hidden">
            <div class="bg-blue-50 border border-blue-200 rounded-lg p-6">
                <div class="flex items-center space-x-4">
                    <div class="flex-shrink-0">
                        <svg class="animate-spin h-8 w-8 text-blue-600" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                        </svg>
                    </div>
                    <div class="flex-1">
                        <h3 class="text-lg font-medium text-blue-900">Generating Export</h3>
                        <p class="text-blue-700">Please wait while we prepare your training data export...</p>
                        <div class="mt-4">
                            <div class="bg-blue-200 rounded-full h-2">
                                <div id="progress-bar" class="bg-blue-600 h-2 rounded-full transition-all duration-300" style="width: 0%"></div>
                            </div>
                            <p id="progress-text" class="text-sm text-blue-600 mt-2">Initializing...</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- Main Form Content --}}
        <div class="grid grid-cols-1 lg:grid-cols-4 gap-6">
            {{-- Main Form --}}
            <div class="lg:col-span-3">
                {{ $this->form }}
            </div>

            {{-- Help Sidebar --}}
            <div class="lg:col-span-1 space-y-6">
                {{-- Quick Start Guide --}}
                <div class="bg-gradient-to-br from-blue-50 to-indigo-50 border border-blue-200 rounded-lg p-6">
                    <h3 class="text-lg font-semibold text-blue-900 mb-4 flex items-center">
                        <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                        Quick Start Guide
                    </h3>
                    <ol class="text-sm space-y-3 text-blue-800">
                        <li class="flex items-start space-x-2">
                            <span class="flex-shrink-0 w-5 h-5 bg-blue-600 text-white rounded-full text-xs flex items-center justify-center font-medium">1</span>
                            <span><strong>Choose Export Type:</strong> Select whether you want training participants, participant history, or training summaries.</span>
                        </li>
                        <li class="flex items-start space-x-2">
                            <span class="flex-shrink-0 w-5 h-5 bg-blue-600 text-white rounded-full text-xs flex items-center justify-center font-medium">2</span>
                            <span><strong>Select Data:</strong> Pick specific trainings or participants you want to export.</span>
                        </li>
                        <li class="flex items-start space-x-2">
                            <span class="flex-shrink-0 w-5 h-5 bg-blue-600 text-white rounded-full text-xs flex items-center justify-center font-medium">3</span>
                            <span><strong>Apply Filters:</strong> Narrow down by county, facility, cadre, or dates.</span>
                        </li>
                        <li class="flex items-start space-x-2">
                            <span class="flex-shrink-0 w-5 h-5 bg-blue-600 text-white rounded-full text-xs flex items-center justify-center font-medium">4</span>
                            <span><strong>Choose Fields:</strong> Select which data columns to include.</span>
                        </li>
                        <li class="flex items-start space-x-2">
                            <span class="flex-shrink-0 w-5 h-5 bg-blue-600 text-white rounded-full text-xs flex items-center justify-center font-medium">5</span>
                            <span><strong>Generate Export:</strong> Click the export button to download your Excel file.</span>
                        </li>
                    </ol>
                </div>

                {{-- Export Types Explanation --}}
                <div class="bg-white border border-gray-200 rounded-lg p-6">
                    <h3 class="text-lg font-semibold text-gray-900 mb-4">Export Types</h3>
                    <div class="space-y-4 text-sm">
                        <div class="border-l-4 border-green-500 pl-4">
                            <h4 class="font-medium text-gray-900">Training Participants</h4>
                            <p class="text-gray-600">Export detailed participant lists from selected trainings. Each training becomes a separate worksheet with participant details, assessments, and status.</p>
                        </div>
                        <div class="border-l-4 border-blue-500 pl-4">
                            <h4 class="font-medium text-gray-900">Participant Training History</h4>
                            <p class="text-gray-600">Export complete training history for selected participants. Shows all trainings each person has attended across the system.</p>
                        </div>
                        <div class="border-l-4 border-purple-500 pl-4">
                            <h4 class="font-medium text-gray-900">Training Summary Report</h4>
                            <p class="text-gray-600">Export high-level overview and statistics of selected trainings. Perfect for management reports and analytics.</p>
                        </div>
                    </div>
                </div>

                {{-- Tips & Best Practices --}}
                <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-6">
                    <h3 class="text-lg font-semibold text-yellow-900 mb-4 flex items-center">
                        <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z"></path>
                        </svg>
                        Pro Tips
                    </h3>
                    <ul class="text-sm space-y-2 text-yellow-800">
                        <li class="flex items-start space-x-2">
                            <svg class="w-4 h-4 mt-0.5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"></path>
                            </svg>
                            <span>Use date filters to limit large exports and improve performance</span>
                        </li>
                        <li class="flex items-start space-x-2">
                            <svg class="w-4 h-4 mt-0.5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"></path>
                            </svg>
                            <span>Select only the fields you need to keep files manageable</span>
                        </li>
                        <li class="flex items-start space-x-2">
                            <svg class="w-4 h-4 mt-0.5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"></path>
                            </svg>
                            <span>Include assessment data for comprehensive participant analysis</span>
                        </li>
                        <li class="flex items-start space-x-2">
                            <svg class="w-4 h-4 mt-0.5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"></path>
                            </svg>
                            <span>Use Excel format for multiple worksheets and better formatting</span>
                        </li>
                        <li class="flex items-start space-x-2">
                            <svg class="w-4 h-4 mt-0.5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"></path>
                            </svg>
                            <span>Large exports may take a few minutes - please be patient</span>
                        </li>
                    </ul>
                </div>

                {{-- Sample Export Structure --}}
                <div class="bg-gray-50 border border-gray-200 rounded-lg p-6">
                    <h3 class="text-lg font-semibold text-gray-900 mb-4">Sample Export Structure</h3>
                    <div class="text-sm space-y-2">
                        <div class="font-medium text-gray-800">📊 Summary Dashboard</div>
                        <div class="ml-4 text-gray-600">- Export overview and statistics</div>
                        <div class="ml-4 text-gray-600">- County breakdown</div>
                        <div class="ml-4 text-gray-600">- Completion rates</div>
                        
                        <div class="font-medium text-gray-800 mt-3">📋 Training Worksheets</div>
                        <div class="ml-4 text-gray-600">- One tab per training</div>
                        <div class="ml-4 text-gray-600">- Participant details</div>
                        <div class="ml-4 text-gray-600">- Assessment results</div>
                        <div class="ml-4 text-gray-600">- Training information</div>
                    </div>
                </div>

                {{-- Contact Support --}}
                <div class="bg-red-50 border border-red-200 rounded-lg p-6">
                    <h3 class="text-lg font-semibold text-red-900 mb-2 flex items-center">
                        <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18.364 5.636l-3.536 3.536m0 5.656l3.536 3.536M9.172 9.172L5.636 5.636m3.536 9.192L5.636 18.364M12 2.25a.75.75 0 01.75.75v2.25a.75.75 0 01-1.5 0V3a.75.75 0 01.75-.75zM12 18a.75.75 0 01.75.75v2.25a.75.75 0 01-1.5 0V18.75A.75.75 0 0112 18z"></path>
                        </svg>
                        Need Help?
                    </h3>
                    <p class="text-sm text-red-700 mb-3">If you encounter any issues or need assistance with exports, please contact the system administrator.</p>
                    <a href="mailto:support@example.com" class="inline-flex items-center text-sm text-red-800 hover:text-red-900">
                        <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 4.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"></path>
                        </svg>
                        Email Support
                    </a>
                </div>
            </div>
        </div>
    </div>

    {{-- JavaScript for Progress Bar and Form Enhancements --}}
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Export progress simulation
            function showExportProgress() {
                const progressContainer = document.getElementById('export-progress');
                const progressBar = document.getElementById('progress-bar');
                const progressText = document.getElementById('progress-text');
                
                if (!progressContainer) return;
                
                progressContainer.classList.remove('hidden');
                
                const steps = [
                    { progress: 10, text: 'Validating export configuration...' },
                    { progress: 25, text: 'Loading training data...' },
                    { progress: 45, text: 'Processing participant information...' },
                    { progress: 65, text: 'Calculating assessment results...' },
                    { progress: 80, text: 'Formatting Excel worksheets...' },
                    { progress: 95, text: 'Finalizing export file...' },
                    { progress: 100, text: 'Export completed! Download starting...' }
                ];
                
                let currentStep = 0;
                const stepInterval = setInterval(() => {
                    if (currentStep < steps.length) {
                        const step = steps[currentStep];
                        progressBar.style.width = step.progress + '%';
                        progressText.textContent = step.text;
                        currentStep++;
                    } else {
                        clearInterval(stepInterval);
                        setTimeout(() => {
                            progressContainer.classList.add('hidden');
                        }, 2000);
                    }
                }, 1000);
            }
            
            // Listen for form submission to trigger progress
            const form = document.querySelector('form');
            if (form) {
                form.addEventListener('submit', function(e) {
                    // Check if this is an export action
                    const submitButton = document.activeElement;
                    if (submitButton && submitButton.textContent.includes('Export')) {
                        showExportProgress();
                    }
                });
            }
            
            // Dynamic field updates based on export type
            const exportTypeSelect = document.querySelector('[name="export_type"]');
            if (exportTypeSelect) {
                exportTypeSelect.addEventListener('change', function() {
                    // Add visual feedback for export type selection
                    const selectedOption = this.options[this.selectedIndex];
                    console.log('Export type changed to:', selectedOption.value);
                    
                    // You can add more dynamic behavior here
                    // For example, showing/hiding relevant help text
                });
            }
            
            // Auto-save form state to localStorage for better UX
            function saveFormState() {
                const formData = new FormData(form);
                const formState = {};
                for (let [key, value] of formData.entries()) {
                    if (formState[key]) {
                        // Handle multiple values (checkboxes, multi-selects)
                        if (Array.isArray(formState[key])) {
                            formState[key].push(value);
                        } else {
                            formState[key] = [formState[key], value];
                        }
                    } else {
                        formState[key] = value;
                    }
                }
                // Note: Commented out localStorage due to Claude.ai restrictions
                // localStorage.setItem('training_export_form', JSON.stringify(formState));
            }
            
            // Save form state periodically
            if (form) {
                form.addEventListener('change', saveFormState);
            }
        });
    </script>

    {{-- Additional Styling --}}
    <style>
        /* Custom styling for better UX */
        .tab-content {
            transition: all 0.3s ease-in-out;
        }
        
        .checkbox-list-item:hover {
            background-color: #f8fafc;
        }
        
        .export-preview-box {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 1rem;
            border-radius: 0.5rem;
            margin-top: 1rem;
        }
        
        .field-selection-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 0.5rem;
        }
        
        /* Progress bar animation */
        #progress-bar {
            transition: width 0.5s ease-in-out;
        }
        
        /* Form section spacing */
        .filament-forms-section-component {
            margin-bottom: 2rem;
        }
        
        /* Responsive adjustments */
        @media (max-width: 768px) {
            .lg\:col-span-3 {
                grid-column: span 1;
            }
            
            .lg\:col-span-1 {
                grid-column: span 1;
                order: -1; /* Show help sidebar first on mobile */
            }
        }
    </style>
</x-filament-panels::page>