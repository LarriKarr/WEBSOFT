// assets/environments/gym.js
import { createParquetTexture, createWallpaperTexture, createStonePathTexture, createSkyTexture, createPottedPlant } from '../core/textures.js';

export default {
    id: 'gym',
    name: 'Тренажёрный зал с рингом',
    category: 'environments',
    build(context) {
        const { scene, envGroup, isNight, addStaticCollider, interactiveElements } = context;
        scene.background = new THREE.Color(isNight ? 0x090d16 : 0x111827);

        const matFloor = new THREE.Mesh(new THREE.PlaneGeometry(16, 16), new THREE.MeshStandardMaterial({ color: 0x1f2937, roughness: 0.85 }));
        matFloor.rotation.x = -Math.PI / 2; envGroup.add(matFloor);

        const ring = new THREE.Group();
        const canvasFloor = new THREE.Mesh(new THREE.BoxGeometry(4.4, 0.45, 4.4), new THREE.MeshStandardMaterial({ color: 0x2563eb }));
        canvasFloor.position.y = 0.225; ring.add(canvasFloor);

        const cornerColors = [0xdc2626, 0xffffff, 0x2563eb, 0xffffff];
        [[-2.05, -2.05], [2.05, -2.05], [2.05, 2.05], [-2.05, 2.05]].forEach(([px, pz], idx) => {
            const post = new THREE.Mesh(new THREE.CylinderGeometry(0.09, 0.09, 1.45), new THREE.MeshStandardMaterial({ color: cornerColors[idx] }));
            post.position.set(px, 0.95, pz); ring.add(post);
        });
        [0.75, 1.1, 1.45].forEach(ry => {
            const ropeMat = new THREE.MeshStandardMaterial({ color: ry === 1.1 ? 0xffffff : 0xdc2626 });
            const r1 = new THREE.Mesh(new THREE.CylinderGeometry(0.025, 0.025, 4.1), ropeMat); r1.rotation.z = Math.PI / 2; r1.position.set(0, ry, -2.05);
            const r2 = r1.clone(); r2.position.z = 2.05;
            const r3 = new THREE.Mesh(new THREE.CylinderGeometry(0.025, 0.025, 4.1), ropeMat); r3.rotation.x = Math.PI / 2; r3.position.set(-2.05, ry, 0);
            const r4 = r3.clone(); r4.position.x = 2.05;
            ring.add(r1, r2, r3, r4);
        });
        envGroup.add(ring);
        addStaticCollider(0, 0, 4.4, 4.4);

        [[-4.8, -3.5], [4.8, -3.5], [-4.8, 3.2], [4.8, 3.2]].forEach(([gx, gz]) => {
            const machine = new THREE.Mesh(new THREE.BoxGeometry(1.3, 1.6, 1.8), new THREE.MeshStandardMaterial({ color: 0x475569 }));
            machine.position.set(gx, 0.8, gz); envGroup.add(machine);
            addStaticCollider(gx, gz, 1.4, 1.8);
        });
    }
};
