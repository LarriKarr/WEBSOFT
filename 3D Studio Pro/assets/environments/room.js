// assets/environments/room.js
import { createParquetTexture, createWallpaperTexture, createStonePathTexture, createSkyTexture, createPottedPlant } from '../core/textures.js';

export default {
    id: 'room',
    name: 'Комната',
    category: 'environments',
    build(context) {
        const { scene, envGroup, isNight, addStaticCollider, interactiveElements } = context;
        scene.background = new THREE.Color(isNight ? 0x060913 : 0x1e293b);

        const floor = new THREE.Mesh(new THREE.PlaneGeometry(10, 10), new THREE.MeshStandardMaterial({ map: createParquetTexture(), roughness: 0.6 }));
        floor.rotation.x = -Math.PI / 2; floor.receiveShadow = true; envGroup.add(floor);

        const wallMat = new THREE.MeshStandardMaterial({ map: createWallpaperTexture(), roughness: 0.85 });
        const backWall = new THREE.Mesh(new THREE.BoxGeometry(10, 4.2, 0.2), wallMat);
        backWall.position.set(0, 2.1, -5); envGroup.add(backWall);
        const leftWall = new THREE.Mesh(new THREE.BoxGeometry(0.2, 4.2, 10), wallMat);
        leftWall.position.set(-5, 2.1, 0); envGroup.add(leftWall);

        addStaticCollider(0, -5.1, 10.4, 0.4);
        addStaticCollider(-5.1, 0, 0.4, 10.4);
        addStaticCollider(5.1, 0, 0.4, 10.4);
        addStaticCollider(0, 5.1, 10.4, 0.4);

        const winFrame = new THREE.Mesh(new THREE.BoxGeometry(2.4, 1.7, 0.14), new THREE.MeshStandardMaterial({ color: 0xf8fafc }));
        winFrame.position.set(0, 2.25, -4.88); envGroup.add(winFrame);
        const winMesh = new THREE.Mesh(new THREE.PlaneGeometry(2.15, 1.45), new THREE.MeshBasicMaterial({ map: createSkyTexture(isNight, false) }));
        winMesh.position.set(0, 2.25, -4.80);
        interactiveElements.push({ mesh: winMesh, type: 'window' });
        envGroup.add(winMesh);

        const rug = new THREE.Mesh(new THREE.PlaneGeometry(4.2, 3.0), new THREE.MeshStandardMaterial({ color: 0x991b1b, roughness: 0.95 }));
        rug.rotation.x = -Math.PI / 2; rug.position.set(0, 0.005, 0.4); envGroup.add(rug);

        const sofa = new THREE.Group();
        const sMat = new THREE.MeshStandardMaterial({ color: 0x334155 });
        const sBase = new THREE.Mesh(new THREE.BoxGeometry(2.4, 0.45, 0.95), sMat); sBase.position.y = 0.225;
        const sBack = new THREE.Mesh(new THREE.BoxGeometry(2.4, 0.65, 0.22), sMat); sBack.position.set(0, 0.65, -0.36);
        sofa.add(sBase, sBack); sofa.position.set(2.4, 0, -3.9);
        sofa.traverse(m => { if (m.isMesh) m.castShadow = true; });
        envGroup.add(sofa);
        addStaticCollider(2.4, -3.9, 2.4, 0.95);

        const shelf = new THREE.Group();
        const woodMat = new THREE.MeshStandardMaterial({ color: 0x5c3a21 });
        const frame = new THREE.Mesh(new THREE.BoxGeometry(1.3, 2.4, 0.45), woodMat); frame.position.y = 1.2;
        shelf.add(frame);
        const bCols = [0xdc2626, 0x2563eb, 0x16a34a, 0xd97706];
        for (let r = 0; r < 4; r++) {
            for (let b = 0; b < 5; b++) {
                const bk = new THREE.Mesh(new THREE.BoxGeometry(0.16, 0.32, 0.32), new THREE.MeshStandardMaterial({ color: bCols[(r + b) % 4] }));
                bk.position.set(-0.42 + b * 0.21, 0.45 + r * 0.52, 0.08);
                shelf.add(bk);
            }
        }
        shelf.position.set(-3.4, 0, -4.4);
        envGroup.add(shelf);
        addStaticCollider(-3.4, -4.4, 1.3, 0.5);

        const table = new THREE.Mesh(new THREE.BoxGeometry(1.3, 0.52, 0.8), woodMat);
        table.position.set(-2.6, 0.26, -1.5); table.castShadow = true; envGroup.add(table);
        addStaticCollider(-2.6, -1.5, 1.3, 0.8);

        [[-3.8, -1.5], [-2.6, -2.6]].forEach(([ax, az]) => {
            const ch = new THREE.Mesh(new THREE.BoxGeometry(0.75, 0.65, 0.75), new THREE.MeshStandardMaterial({ color: 0xb45309 }));
            ch.position.set(ax, 0.325, az); ch.castShadow = true; envGroup.add(ch);
            addStaticCollider(ax, az, 0.8, 0.8);
        });

        const lampGroup = new THREE.Group();
        const pole = new THREE.Mesh(new THREE.CylinderGeometry(0.03, 0.04, 1.9), new THREE.MeshStandardMaterial({ color: 0x1e293b }));
        pole.position.y = 0.95;
        const shadeMat = new THREE.MeshStandardMaterial({ color: 0xfef08a, emissive: 0xf59e0b, emissiveIntensity: 0.85 });
        const shade = new THREE.Mesh(new THREE.CylinderGeometry(0.22, 0.35, 0.42, 16), shadeMat);
        shade.position.y = 1.9;
        const ptLight = new THREE.PointLight(0xfbbf24, 1.2, 6);
        ptLight.position.set(0, 1.8, 0);
        lampGroup.add(pole, shade, ptLight);
        lampGroup.position.set(4.1, 0, -4.1);
        envGroup.add(lampGroup);
        interactiveElements.push({ mesh: shade, type: 'lamp', on: true, mat: shadeMat, light: ptLight });
        addStaticCollider(4.1, -4.1, 0.5, 0.5);

        createPottedPlant(-4.3, 4.1, envGroup, addStaticCollider);
        createPottedPlant(4.3, 4.1, envGroup, addStaticCollider);
    }
};
