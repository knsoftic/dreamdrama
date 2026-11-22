import axios from 'axios';

/**
 * Admin video upload form: reads the duration in the browser, grabs a
 * thumbnail frame, and uploads with a progress bar.
 */
export default function videoUpload(initial = {}) {
    return {
        duration: initial.duration || '',
        thumb: initial.thumb || null,
        thumbData: '',
        fileName: '',
        progress: 0,
        uploading: false,
        errors: {},

        async pick(e) {
            const file = e.target.files[0];
            if (!file) return;
            this.fileName = `${file.name} (${(file.size / 1048576).toFixed(1)} MB)`;
            const url = URL.createObjectURL(file);
            const video = document.createElement('video');
            video.preload = 'metadata';
            video.muted = true;
            video.playsInline = true;
            video.src = url;

            try {
                await new Promise((res, rej) => {
                    video.onloadedmetadata = res;
                    video.onerror = rej;
                });

                // Some recorded webm files report Infinity until you seek to the end.
                if (!Number.isFinite(video.duration)) {
                    video.currentTime = 1e7;
                    await new Promise((res) => { video.ontimeupdate = () => { video.ontimeupdate = null; res(); }; });
                }
                if (Number.isFinite(video.duration) && video.duration > 0) {
                    this.duration = Math.max(1, Math.round(video.duration));
                }

                video.currentTime = Math.min(1, (video.duration || 2) / 3);
                await new Promise((res) => { video.onseeked = res; });

                const canvas = document.createElement('canvas');
                const scale = Math.min(1, 720 / Math.max(video.videoWidth, video.videoHeight));
                canvas.width = Math.round(video.videoWidth * scale);
                canvas.height = Math.round(video.videoHeight * scale);
                canvas.getContext('2d').drawImage(video, 0, 0, canvas.width, canvas.height);
                this.thumbData = canvas.toDataURL('image/jpeg', 0.8);
                this.thumb = this.thumbData;
            } catch (err) {
                // Duration can still be typed by hand.
            } finally {
                URL.revokeObjectURL(url);
            }
        },

        async submit(e) {
            const form = e.target;
            const fd = new FormData(form);
            this.uploading = true;
            this.errors = {};
            this.progress = 0;

            try {
                const res = await axios.post(form.action, fd, {
                    headers: { Accept: 'application/json' },
                    onUploadProgress: (p) => { this.progress = Math.round((100 * p.loaded) / (p.total || 1)); },
                });
                window.location.href = res.data?.redirect || form.dataset.back;
            } catch (err) {
                this.uploading = false;
                if (err.response?.status === 422) {
                    this.errors = err.response.data.errors || {};
                } else if (err.response?.status === 413) {
                    this.errors = { video: ['File is too large (server limit).'] };
                } else {
                    this.errors = { video: ['Upload failed. Please try again.'] };
                }
            }
        },
    };
}
