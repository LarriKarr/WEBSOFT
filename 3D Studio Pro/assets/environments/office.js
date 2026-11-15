// assets/environments/office.js
import { createParquetTexture, createWallpaperTexture, createStonePathTexture, createSkyTexture, createPottedPlant } from '../core/textures.js';

export default {
    id: 'office',
    name: 'Офис',
    category: 'environments',
    build(context) {
        const { scene, envGroup, isNight, addStaticCollider, interactiveElements } = context;
        scene.background = new THREE.Color(isNight ? 0x050811 : 0x1e293b);

        const carpet = new THREE.Mesh(new THREE.PlaneGeometry(14, 14), new THREE.MeshStandardMaterial({ color: 0x334155, roughness: 0.9 }));
        carpet.rotation.x = -Math.PI / 2; carpet.receiveShadow = true; envGroup.add(carpet);

        const metroMesh = new THREE.Mesh(new THREE.PlaneGeometry(13.6, 4.8), new THREE.MeshBasicMaterial({ map: createSkyTexture(isNight, true) }));
        metroMesh.position.set(0, 2.4, -6.5);
        interactiveElements.push({ mesh: metroMesh, type: 'window' });
        envGroup.add(metroMesh);

        addStaticCollider(0, -6.6, 14, 0.4);
        addStaticCollider(-6.6, 0, 0.4, 14);
        addStaticCollider(6.6, 0, 0.4, 14);

        const woodDark = new THREE.MeshStandardMaterial({ color: 0x3f2305 });
        const deskLight = new THREE.MeshStandardMaterial({ color: 0xe2e8f0 });

        const mainDesk = new THREE.Mesh(new THREE.BoxGeometry(2.6, 0.78, 1.1), woodDark);
        mainDesk.position.set(0, 0.39, -3.6); mainDesk.castShadow = true; envGroup.add(mainDesk);
        const execChair = new THREE.Mesh(new THREE.BoxGeometry(0.75, 1.15, 0.75), new THREE.MeshStandardMaterial({ color: 0x111827 }));
        execChair.position.set(0, 0.575, -4.5); envGroup.add(execChair);
        const pc1 = new THREE.Mesh(new THREE.BoxGeometry(0.75, 0.48, 0.06), new THREE.MeshStandardMaterial({ color: 0x0f172a }));
        pc1.position.set(0, 1.05, -3.5); envGroup.add(pc1);

        const lamp1Mat = new THREE.MeshStandardMaterial({ color: 0xfef08a, emissive: 0xfacc15, emissiveIntensity: 0.9 });
        const lamp1 = new THREE.Mesh(new THREE.SphereGeometry(0.14, 12, 12), lamp1Mat);
        lamp1.position.set(0.95, 0.92, -3.4); envGroup.add(lamp1);
        interactiveElements.push({ mesh: lamp1, type: 'lamp', on: true, mat: lamp1Mat });
        addStaticCollider(0, -3.8, 2.6, 1.6);

        const confTable = new THREE.Mesh(new THREE.BoxGeometry(4.4, 0.75, 1.4), deskLight);
        confTable.position.set(0, 0.375, -0.6); confTable.castShadow = true; envGroup.add(confTable);
        const phone = new THREE.Mesh(new THREE.BoxGeometry(0.28, 0.1, 0.24), new THREE.MeshStandardMaterial({ color: 0x111827 }));
        phone.position.set(0, 0.8, -0.6); envGroup.add(phone);
        addStaticCollider(0, -0.6, 4.4, 1.4);

        const secDesk = new THREE.Mesh(new THREE.BoxGeometry(1.6, 0.75, 0.9), deskLight);
        secDesk.position.set(-4.4, 0.375, -2.5); secDesk.castShadow = true; envGroup.add(secDesk);
        const lamp2Mat = new THREE.MeshStandardMaterial({ color: 0xfef08a, emissive: 0xfacc15, emissiveIntensity: 0.9 });
        const lamp2 = new THREE.Mesh(new THREE.SphereGeometry(0.12, 12, 12), lamp2Mat);
        lamp2.position.set(-4.1, 0.88, -2.4); envGroup.add(lamp2);
        interactiveElements.push({ mesh: lamp2, type: 'lamp', on: true, mat: lamp2Mat });
        addStaticCollider(-4.4, -2.5, 1.6, 0.9);

        createPottedPlant(-5.6, -5.6, envGroup, addStaticCollider);
        createPottedPlant(5.6, -5.6, envGroup, addStaticCollider);
    }
};
