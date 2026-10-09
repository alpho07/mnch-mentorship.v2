/**
 * Offline resource files.
 *
 * Module resource files are fetched through short-lived signed URLs, so they
 * can only be downloaded while online. Once fetched they are kept in
 * IndexedDB (as a Blob) and can be opened/shared again with no connection.
 *
 * On the Android/iOS app the file is handed to the OS share sheet (save to
 * Files, open in a PDF viewer, WhatsApp…); in a browser it is a normal download.
 */
import { Capacitor } from "@capacitor/core";
import { Filesystem, Directory } from "@capacitor/filesystem";
import { Share } from "@capacitor/share";
import offlineStore from "./offline-store.js";

export const fileKey = (resourceId) => `resource_${resourceId}`;

export async function savedFileKeys() {
    return new Set(await offlineStore.getFileKeys());
}

/** Download a file (online) and keep a copy for offline use. */
export async function saveFileOffline(resourceId, file) {
    const res = await fetch(file.download_url);
    if (!res.ok) throw new Error(res.status === 403 ? "Download link expired — refresh and try again." : `Download failed (${res.status}).`);
    const blob = await res.blob();
    await offlineStore.saveFile(fileKey(resourceId), {
        blob,
        name: file.name,
        mime: file.mime || blob.type || "application/octet-stream",
        size: blob.size,
        savedAt: Date.now(),
    });
}

export async function removeSavedFile(resourceId) {
    await offlineStore.deleteFile(fileKey(resourceId));
}

function blobToBase64(blob) {
    return new Promise((resolve, reject) => {
        const reader = new FileReader();
        reader.onloadend = () => resolve(String(reader.result).split(",")[1] ?? "");
        reader.onerror = reject;
        reader.readAsDataURL(blob);
    });
}

/** Open/share a previously saved file. */
export async function openSavedFile(resourceId) {
    const saved = await offlineStore.getFile(fileKey(resourceId));
    if (!saved?.blob) throw new Error("This file is not saved on the device.");

    if (Capacitor.isNativePlatform()) {
        const path = `resources/${saved.name}`;
        await Filesystem.writeFile({
            path,
            data: await blobToBase64(saved.blob),
            directory: Directory.Cache,
            recursive: true,
        });
        const { uri } = await Filesystem.getUri({ path, directory: Directory.Cache });
        await Share.share({ title: saved.name, url: uri, dialogTitle: "Open or save file" });
        return;
    }

    const url = URL.createObjectURL(saved.blob);
    const a = document.createElement("a");
    a.href = url;
    a.download = saved.name;
    document.body.appendChild(a);
    a.click();
    a.remove();
    setTimeout(() => URL.revokeObjectURL(url), 60_000);
}
