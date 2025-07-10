{{-- Enhanced Upload Progress Component --}}
<div class="upload-progress-container">
    @if($processingFiles && count($processingFiles) > 0)
        <div class="space-y-4">
            <div class="flex justify-between items-center">
                <h3 class="text-lg font-semibold text-gray-900">
                    Uploading {{ count($processingFiles) }} {{ count($processingFiles) === 1 ? 'file' : 'files' }}
                </h3>
                <span class="text-sm text-gray-500">
                    {{ $successfulFiles ?? 0 }} completed, {{ $failedFiles ?? 0 }} failed
                </span>
            </div>
            
            @foreach($processingFiles as $index => $file)
                <div class="border rounded-lg p-4 bg-white shadow-sm" data-filename="{{ $file['name'] }}">
                    <div class="flex justify-between items-start mb-2">
                        <div>
                            <h4 class="font-medium text-gray-900">{{ $file['name'] }}</h4>
                            <p class="text-sm text-gray-500">{{ $file['size'] }}</p>
                        </div>
                        <div class="flex items-center space-x-2">
                            @if($file['status'] === 'complete')
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">
                                    <svg class="w-4 h-4 mr-1" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"></path>
                                    </svg>
                                    Complete
                                </span>
                            @elseif($file['status'] === 'error')
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-800">
                                    <svg class="w-4 h-4 mr-1" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"></path>
                                    </svg>
                                    Error
                                </span>
                            @else
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800">
                                    <svg class="animate-spin w-4 h-4 mr-1" fill="none" viewBox="0 0 24 24">
                                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                    </svg>
                                    <span class="upload-phase">
                                        @if(isset($file['phase']) && $file['phase'] === 'uploading')
                                            Uploading to cloud...
                                        @else
                                            Processing...
                                        @endif
                                    </span>
                                </span>
                            @endif
                        </div>
                    </div>
                    
                    @if($file['status'] === 'uploading' || $file['status'] === 'processing')
                        {{-- Progress Bar --}}
                        <div class="w-full bg-gray-200 rounded-full h-2 mb-3">
                            <div class="progress-bar bg-blue-600 h-2 rounded-full transition-all duration-300"
                                 style="width: {{ $file['upload_progress'] ?? 0 }}%"
                                 aria-valuenow="{{ $file['upload_progress'] ?? 0 }}"
                                 aria-valuemin="0"
                                 aria-valuemax="100">
                            </div>
                        </div>
                        
                        {{-- Progress Details --}}
                        <div class="flex justify-between items-center text-sm text-gray-600">
                            <span class="progress-text">
                                @if(isset($file['bytes_uploaded']) && isset($file['total_bytes']))
                                    {{ $this->formatFileSize($file['bytes_uploaded']) }} / {{ $this->formatFileSize($file['total_bytes']) }}
                                    ({{ number_format($file['upload_progress'] ?? 0, 1) }}%)
                                @else
                                    {{ number_format($file['upload_progress'] ?? 0, 1) }}%
                                @endif
                            </span>
                            
                            <div class="flex items-center space-x-4">
                                @if(isset($file['upload_speed']) && $file['upload_speed'])
                                    <span class="upload-speed">{{ $this->formatFileSize((int) $file['upload_speed']) }}/s</span>
                                @endif
                                
                                @if(isset($file['eta']) && $file['eta'])
                                    <span class="upload-eta">
                                        ETA: 
                                        @if($file['eta'] < 60)
                                            {{ $file['eta'] }}s
                                        @elseif($file['eta'] < 3600)
                                            {{ floor($file['eta'] / 60) }}m {{ $file['eta'] % 60 }}s
                                        @else
                                            {{ floor($file['eta'] / 3600) }}h {{ floor(($file['eta'] % 3600) / 60) }}m
                                        @endif
                                    </span>
                                @endif
                            </div>
                        </div>
                    @endif
                    
                    @if($file['status'] === 'error' && isset($file['error']))
                        <div class="mt-2 text-sm text-red-600">
                            {{ $file['error'] }}
                        </div>
                    @endif
                </div>
            @endforeach
        </div>
    @endif
</div>

{{-- Include the JavaScript for real-time updates --}}
@push('scripts')
    <script src="{{ asset('js/cloud-upload-progress.js') }}"></script>
    <script>
        // Initialize cloud upload progress tracking
        document.addEventListener('DOMContentLoaded', function() {
            @auth
                // Set up session tracking when upload starts
                const sessionId = '{{ $uploadSessionId ?? '' }}';
                if (sessionId) {
                    window.CloudUploadProgress.subscribe({{ auth()->id() }}, sessionId);
                }
            @endauth
        });
    </script>
@endpush