// assets/environments/park.js
import { createParquetTexture, createWallpaperTexture, createStonePathTexture, createSkyTexture, createPottedPlant } from '../core/textures.js';

export default {
    id: 'park',
    name: 'Парк',
    category: 'environments',
    build(context) {
        const { scene, envGroup, isNight, addStaticCollider, interactiveElements } = context;
        scene.background = new THREE.Color(isNight ? 0x040814 : 0x38bdf8);

        const grass = new THREE.Mesh(new THREE.PlaneGeometry(24, 24), new THREE.MeshStandardMaterial({ color: 0x15803d, roughness: 0.95 }));
        grass.rotation.x = -Math.PI / 2; grass.receiveShadow = true; envGroup.add(grass);

        const skyWall = new THREE.Mesh(new THREE.PlaneGeometry(26, 10), new THREE.MeshBasicMaterial({ map: createSkyTexture(isNight, false) }));
        skyWall.position.set(0, 5, -11);
        interactiveElements.push({ mesh: skyWall, type: 'window' });
        envGroup.add(skyWall);

        const pathTex = createStonePathTexture();
        const pathMat = new THREE.MeshStandardMaterial({ map: pathTex });
        const mainPath = new THREE.Mesh(new THREE.PlaneGeometry(2.4, 24), pathMat);
        mainPath.rotation.x = -Math.PI / 2; mainPath.position.y = 0.008; envGroup.add(mainPath);
        const crossPath = new THREE.Mesh(new THREE.PlaneGeometry(24, 2.2), pathMat);
        crossPath.rotation.x = -Math.PI / 2; crossPath.position.set(0, 0.009, -1.5); envGroup.add(crossPath);

        const trunkMat = new THREE.MeshStandardMaterial({ color: 0x451a03 });
        const leafMat = new THREE.MeshStandardMaterial({ color: 0x166534 });
        [[-2.8, -4.2], [2.8, -4.0], [-3.2, 1.8], [3.0, 2.2], [-5.2, -3.5], [5.0, -3.2]].forEach(([tx, tz]) => {
            const tr = new THREE.Group();
            const trunk = new THREE.Mesh(new THREE.CylinderGeometry(0.22, 0.32, 2.2, 10), trunkMat); trunk.position.y = 1.1;
            const c1 = new THREE.Mesh(new THREE.DodecahedronGeometry(1.25), leafMat); c1.position.y = 2.7;
            const c2 = new THREE.Mesh(new THREE.DodecahedronGeometry(0.95), leafMat); c2.position.set(0.35, 3.3, 0.2);
            tr.add(trunk, c1, c2); tr.position.set(tx, 0, tz);
            tr.traverse(m => { if (m.isMesh) m.castShadow = true; });
            envGroup.add(tr);
            addStaticCollider(tx, tz, 0.85, 0.85);
        });

        [[-2.0, -0.2, Math.PI / 2], [2.0, 0.4, -Math.PI / 2]].forEach(([bx, bz, rot]) => {
            const bench = new THREE.Mesh(new THREE.BoxGeometry(1.5, 0.45, 0.55), new THREE.MeshStandardMaterial({ color: 0x78350f }));
            bench.position.set(bx, 0.225, bz); bench.rotation.y = rot; bench.castShadow = true; envGroup.add(bench);
            addStaticCollider(bx, bz, 0.7, 1.5);

            const binX = bx; const binZ = bz + 1.15;
            const bin = new THREE.Mesh(new THREE.CylinderGeometry(0.24, 0.2, 0.55, 12), new THREE.MeshStandardMaterial({ color: 0x334155 }));
            bin.position.set(binX, 0.275, binZ);
            const trashHeap = new THREE.Mesh(new THREE.DodecahedronGeometry(0.22), new THREE.MeshStandardMaterial({ color: 0xd97706 }));
            trashHeap.position.set(binX, 0.58, binZ); trashHeap.visible = false;
            envGroup.add(bin, trashHeap);
            interactiveElements.push({ mesh: bin, type: 'bin', full: false, trashMesh: trashHeap });
            addStaticCollider(binX, binZ, 0.5, 0.5);
        });
    }
};
