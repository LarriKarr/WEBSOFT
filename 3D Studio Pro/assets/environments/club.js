// assets/environments/club.js
import { createParquetTexture, createWallpaperTexture, createStonePathTexture, createSkyTexture, createPottedPlant } from '../core/textures.js';

export default {
    id: 'club',
    name: 'Заведение: Бар и Казино',
    category: 'environments',
    build(context) {
        const { scene, envGroup, isNight, addStaticCollider, interactiveElements } = context;
        scene.background = new THREE.Color(isNight ? 0x040208 : 0x09050f);

        const floor = new THREE.Mesh(new THREE.PlaneGeometry(16, 16), new THREE.MeshStandardMaterial({ color: 0x111827, roughness: 0.4 }));
        floor.rotation.x = -Math.PI / 2; envGroup.add(floor);

        const bar = new THREE.Mesh(new THREE.BoxGeometry(3.8, 1.1, 1.0), new THREE.MeshStandardMaterial({ color: 0x78350f }));
        bar.position.set(-4.2, 0.55, -3.2); envGroup.add(bar);
        addStaticCollider(-4.2, -3.2, 3.8, 1.0);

        [-5.4, -4.2, -3.0].forEach(sx => {
            const stool = new THREE.Mesh(new THREE.CylinderGeometry(0.24, 0.2, 0.72, 12), new THREE.MeshStandardMaterial({ color: 0xdc2626 }));
            stool.position.set(sx, 0.36, -2.1); envGroup.add(stool);
            addStaticCollider(sx, -2.1, 0.5, 0.5);
        });

        const backShelf = new THREE.Mesh(new THREE.BoxGeometry(3.8, 2.2, 0.4), new THREE.MeshStandardMaterial({ color: 0x1e293b }));
        backShelf.position.set(-4.2, 1.1, -5.2); envGroup.add(backShelf);
        addStaticCollider(-4.2, -5.2, 3.8, 0.4);

        [-5.2, -4.6, -4.0, -3.4].forEach((bx, idx) => {
            const bMat = new THREE.MeshStandardMaterial({ color: idx % 2 === 0 ? 0x16a34a : 0xd97706 });
            const bottle = new THREE.Mesh(new THREE.CylinderGeometry(0.06, 0.08, 0.38, 10), bMat);
            bottle.position.set(bx, 1.32, -3.1); envGroup.add(bottle);
            interactiveElements.push({ mesh: bottle, type: 'bottle', visible: true });
        });

        const pokerTable = new THREE.Mesh(new THREE.CylinderGeometry(1.35, 1.35, 0.78, 20), new THREE.MeshStandardMaterial({ color: 0x15803d }));
        pokerTable.position.set(4.0, 0.39, -1.8); envGroup.add(pokerTable);
        addStaticCollider(4.0, -1.8, 2.7, 2.7);

        [2.8, 4.0, 5.2].forEach((mx, i) => {
            const slot = new THREE.Mesh(new THREE.BoxGeometry(0.85, 1.85, 0.8), new THREE.MeshStandardMaterial({ color: i === 1 ? 0xdc2626 : 0xfacc15 }));
            slot.position.set(mx, 0.925, -4.8); envGroup.add(slot);
            addStaticCollider(mx, -4.8, 0.9, 0.85);
        });

        const strobeGroup = new THREE.Group();
        const dome = new THREE.Mesh(new THREE.SphereGeometry(0.38, 16, 16), new THREE.MeshStandardMaterial({ color: 0xffffff, emissive: 0xe879f9, emissiveIntensity: 0.9 }));
        const beam1 = new THREE.Mesh(new THREE.ConeGeometry(0.9, 3.5, 12, 1, true), new THREE.MeshBasicMaterial({ color: 0xec4899, transparent: true, opacity: 0.28, side: THREE.DoubleSide }));
        beam1.position.set(1.2, -1.5, 0); beam1.rotation.z = 0.6;
        const beam2 = new THREE.Mesh(new THREE.ConeGeometry(0.9, 3.5, 12, 1, true), new THREE.MeshBasicMaterial({ color: 0x38bdf8, transparent: true, opacity: 0.28, side: THREE.DoubleSide }));
        beam2.position.set(-1.2, -1.5, 0); beam2.rotation.z = -0.6;
        strobeGroup.add(dome, beam1, beam2);
        strobeGroup.position.set(0, 4.1, -1.5); envGroup.add(strobeGroup);
        interactiveElements.push({ mesh: dome, group: strobeGroup, beams: [beam1, beam2], type: 'strobe', active: true });
    }
};
