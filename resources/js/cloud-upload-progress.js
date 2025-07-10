/**
 * Cloud Upload Progress Tracking
 * 
 * This script handles the real-time progress updates for cloud storage uploads
 * It listens for Laravel Echo events dispatched by the CloudUploadService
 */

// Make sure Echo is available
if (typeof Echo !== 'undefined') {
    
    /**
     * Listen for cloud upload progress events
     */
    function subscribeToCloudUploadProgress(userId, sessionId) {
        // Listen on user channel
        Echo.private(`user.${userId}.uploads`)
            .listen('.upload.cloud.progress', (event) => {
                handleCloudUploadProgress(event);
            });
        
        // Listen on session channel
        Echo.private(`upload.${sessionId}`)
            .listen('.upload.cloud.progress', (event) => {
                handleCloudUploadProgress(event);
            });
    }
    
    /**
     * Handle cloud upload progress updates
     */
    function handleCloudUploadProgress(event) {
        const {
            session_id,
            filename,
            bytes_uploaded,
            total_bytes,
            percentage,
            speed,
            eta,
            phase,
            formatted_uploaded,
            formatted_total,
            formatted_speed
        } = event;
        
        console.log('Cloud upload progress:', {
            filename,
            percentage: percentage.toFixed(2) + '%',
            speed: formatted_speed,
            eta: eta ? formatETA(eta) : null,
            phase
        });
        
        // Update UI elements
        updateUploadProgressUI(filename, {
            percentage,
            uploaded: formatted_uploaded,
            total: formatted_total,
            speed: formatted_speed,
            eta: eta ? formatETA(eta) : null,
            phase
        });
        
        // If using Livewire, dispatch to component
        if (typeof Livewire !== 'undefined') {
            Livewire.find(getUploaderComponentId())?.call('handleCloudUploadProgress', 
                session_id, filename, bytes_uploaded, total_bytes, percentage, speed, eta
            );
        }
    }
    
    /**
     * Update the upload progress UI
     */
    function updateUploadProgressUI(filename, progress) {
        // Find the upload progress element for this file
        const progressElement = document.querySelector(`[data-filename="${filename}"]`);
        
        if (progressElement) {
            // Update progress bar
            const progressBar = progressElement.querySelector('.progress-bar');
            if (progressBar) {
                progressBar.style.width = `${progress.percentage}%`;
                progressBar.setAttribute('aria-valuenow', progress.percentage);
            }
            
            // Update progress text
            const progressText = progressElement.querySelector('.progress-text');
            if (progressText) {
                progressText.textContent = `${progress.uploaded} / ${progress.total} (${progress.percentage.toFixed(1)}%)`;
            }
            
            // Update speed indicator
            const speedText = progressElement.querySelector('.upload-speed');
            if (speedText && progress.speed) {
                speedText.textContent = progress.speed;
            }
            
            // Update ETA
            const etaText = progressElement.querySelector('.upload-eta');
            if (etaText && progress.eta) {
                etaText.textContent = `ETA: ${progress.eta}`;
            }
            
            // Update phase indicator
            const phaseText = progressElement.querySelector('.upload-phase');
            if (phaseText) {
                phaseText.textContent = progress.phase === 'uploading' ? 'Uploading to cloud...' : 'Processing...';
            }
        }
    }
    
    /**
     * Format ETA seconds into human readable format
     */
    function formatETA(seconds) {
        if (seconds < 60) {
            return `${seconds}s`;
        } else if (seconds < 3600) {
            const minutes = Math.floor(seconds / 60);
            const remainingSeconds = seconds % 60;
            return `${minutes}m ${remainingSeconds}s`;
        } else {
            const hours = Math.floor(seconds / 3600);
            const minutes = Math.floor((seconds % 3600) / 60);
            return `${hours}h ${minutes}m`;
        }
    }
    
    /**
     * Get the Livewire component ID for the uploader
     * This is a helper function - you may need to adjust based on your setup
     */
    function getUploaderComponentId() {
        // Try to find the uploader component
        const uploaderElement = document.querySelector('[wire\\:id]');
        return uploaderElement ? uploaderElement.getAttribute('wire:id') : null;
    }
    
    // Auto-initialize if user ID is available
    if (typeof window.userId !== 'undefined') {
        document.addEventListener('DOMContentLoaded', () => {
            // You'll need to set the session ID when starting an upload
            // This is just an example - adjust based on your implementation
            const sessionId = document.querySelector('[data-upload-session]')?.getAttribute('data-upload-session');
            if (sessionId) {
                subscribeToCloudUploadProgress(window.userId, sessionId);
            }
        });
    }
    
    // Export functions for manual use
    window.CloudUploadProgress = {
        subscribe: subscribeToCloudUploadProgress,
        handle: handleCloudUploadProgress,
        updateUI: updateUploadProgressUI
    };
    
} else {
    console.warn('Laravel Echo not found. Cloud upload progress tracking will not work.');
}