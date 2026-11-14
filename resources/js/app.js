import { createWorker } from 'tesseract.js';

/**
 * Preprocess an uploaded image (auto dark-mode inversion, grayscale, contrast, 2x upscale)
 * into a Blob optimized for OCR recognition of phone numbers.
 */
async function preprocessImageForOcr(file) {
    const bitmap = await createImageBitmap(file);
    const scale = bitmap.width < 1200 ? 2 : 1;
    const canvas = document.createElement('canvas');
    canvas.width = Math.min(bitmap.width * scale, 3200);
    canvas.height = Math.min(bitmap.height * scale, 4800);

    const ctx = canvas.getContext('2d', { willReadFrequently: true });
    ctx.drawImage(bitmap, 0, 0, canvas.width, canvas.height);

    const imgData = ctx.getImageData(0, 0, canvas.width, canvas.height);
    const data = imgData.data;

    let lumSum = 0;
    const pixelCount = data.length / 4;
    for (let i = 0; i < data.length; i += 4) {
        lumSum += 0.299 * data[i] + 0.587 * data[i + 1] + 0.114 * data[i + 2];
    }
    const isDarkBackground = pixelCount > 0 && lumSum / pixelCount < 128;

    for (let i = 0; i < data.length; i += 4) {
        let gray = 0.299 * data[i] + 0.587 * data[i + 1] + 0.114 * data[i + 2];
        if (isDarkBackground) {
            gray = 255 - gray;
        }
        // Mild contrast stretch for crisp digits
        const contrasted = Math.max(0, Math.min(255, (gray - 128) * 1.35 + 128));
        data[i] = contrasted;
        data[i + 1] = contrasted;
        data[i + 2] = contrasted;
    }

    ctx.putImageData(imgData, 0, 0);

    return await new Promise((resolve) => {
        canvas.toBlob((blob) => resolve(blob || file), 'image/png');
    });
}

window.extractTextFromImage = async function extractTextFromImage(file, onProgress) {
    const processed = await preprocessImageForOcr(file);
    const worker = await createWorker('eng', 1, {
        logger: (m) => {
            if (typeof onProgress === 'function' && m?.status === 'recognizing text') {
                onProgress(Math.round((m.progress || 0) * 100));
            }
        },
    });

    try {
        const result = await worker.recognize(processed);
        return result?.data?.text || '';
    } finally {
        await worker.terminate();
    }
};
