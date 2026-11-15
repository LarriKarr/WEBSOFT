// assets/environments/studio.js
import { createParquetTexture, createWallpaperTexture, createStonePathTexture, createSkyTexture, createPottedPlant } from '../core/textures.js';

export default {
    id: 'studio',
    name: 'Студия',
    category: 'environments',
    build(context) {
        const { scene, envGroup, isNight, addStaticCollider, interactiveElements } = context;
        scene.background = new THREE.Color(isNight ? 0x080b12 : 0x111827);

        const floor = new THREE.Mesh(new THREE.PlaneGeometry(16, 16), new THREE.MeshStandardMaterial({ color: 0x1f2937, roughness: 0.7 }));
        floor.rotation.x = -Math.PI / 2; floor.receiveShadow = true; envGroup.add(floor);
    }
};
