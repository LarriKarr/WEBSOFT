// assets/environments/city.js
import { createParquetTexture, createWallpaperTexture, createStonePathTexture, createSkyTexture, createPottedPlant } from '../core/textures.js';

export default {
    id: 'city',
    name: 'Улица города',
    category: 'environments',
    build(context) {
        const { scene, envGroup, isNight, addStaticCollider, interactiveElements } = context;
        scene.background = new THREE.Color(isNight ? 0x020617 : 0x60a5fa);

        const asphalt = new THREE.Mesh(new THREE.PlaneGeometry(28, 28), new THREE.MeshStandardMaterial({ color: 0x1f2937, roughness: 0.85 }));
        asphalt.rotation.x = -Math.PI / 2; asphalt.receiveShadow = true; envGroup.add(asphalt);

        const swLeft = new THREE.Mesh(new THREE.BoxGeometry(8, 0.14, 28), new THREE.MeshStandardMaterial({ color: 0x9ca3af }));
        swLeft.position.set(-8.5, 0.07, 0); envGroup.add(swLeft);
        const swRight = swLeft.clone(); swRight.position.set(8.5, 0.07, 0); envGroup.add(swRight);

        for (let z = -12; z <= 12; z += 2.6) {
            const stripe = new THREE.Mesh(new THREE.PlaneGeometry(0.2, 1.4), new THREE.MeshBasicMaterial({ color: 0xfef08a }));
            stripe.rotation.x = -Math.PI / 2; stripe.position.set(0, 0.01, z); envGroup.add(stripe);
        }

        [[-8, 6.5, 0x475569], [-1, 8.0, 0x334155], [6, 6.0, 0x64748b]].forEach(([bz, h, col], idx) => {
            const bld = new THREE.Group();
            const body = new THREE.Mesh(new THREE.BoxGeometry(4.2, h, 4.6), new THREE.MeshStandardMaterial({ color: col }));
            body.position.y = h / 2; bld.add(body);
            const shop = new THREE.Mesh(new THREE.BoxGeometry(0.15, 1.2, 3.6), new THREE.MeshStandardMaterial({ color: idx === 0 ? 0xef4444 : 0x3b82f6 }));
            shop.position.set(-2.12, 0.7, 0); bld.add(shop);
            for (let wy = 2.0; wy < h - 0.6; wy += 1.3) {
                const balc = new THREE.Mesh(new THREE.BoxGeometry(0.45, 0.25, 2.8), new THREE.MeshStandardMaterial({ color: 0xcbd5e1 }));
                balc.position.set(-2.25, wy, 0); bld.add(balc);
            }
            const sign = new THREE.Mesh(new THREE.BoxGeometry(0.2, 0.9, 3.2), new THREE.MeshStandardMaterial({ color: 0xfacc15, emissive: isNight ? 0xf59e0b : 0x000000, emissiveIntensity: 0.6 }));
            sign.position.set(-1.8, h + 0.5, 0); bld.add(sign);
            bld.position.set(8.8, 0, bz); envGroup.add(bld);
            addStaticCollider(8.8, bz, 4.4, 4.6);
        });

        const museum = new THREE.Group();
        const mBody = new THREE.Mesh(new THREE.BoxGeometry(4.2, 4.2, 6.2), new THREE.MeshStandardMaterial({ color: 0xe2e8f0 }));
        mBody.position.y = 2.1; museum.add(mBody);
        for (let cz = -2.4; cz <= 2.4; cz += 1.2) {
            const colMesh = new THREE.Mesh(new THREE.CylinderGeometry(0.22, 0.25, 3.8, 12), new THREE.MeshStandardMaterial({ color: 0xf8fafc }));
            colMesh.position.set(2.3, 1.9, cz); museum.add(colMesh);
        }
        museum.position.set(-8.8, 0, -4.5); envGroup.add(museum);
        addStaticCollider(-8.8, -4.5, 4.8, 6.2);

        const ped = new THREE.Mesh(new THREE.BoxGeometry(1.4, 1.2, 1.4), new THREE.MeshStandardMaterial({ color: 0x64748b }));
        ped.position.set(-7.5, 0.6, 3.5);
        const statue = new THREE.Mesh(new THREE.CylinderGeometry(0.25, 0.35, 1.8, 12), new THREE.MeshStandardMaterial({ color: 0xd97706, metalness: 0.7 }));
        statue.position.set(-7.5, 2.1, 3.5);
        envGroup.add(ped, statue);
        addStaticCollider(-7.5, 3.5, 1.6, 1.6);

        [-9, -3, 3, 9].forEach(lz => {
            [-4.8, 4.8].forEach(lx => {
                const post = new THREE.Mesh(new THREE.CylinderGeometry(0.06, 0.08, 3.8), new THREE.MeshStandardMaterial({ color: 0x111827 }));
                post.position.set(lx, 1.9, lz);
                const bulbMat = new THREE.MeshStandardMaterial({
                    color: isNight ? 0xfef08a : 0x94a3b8,
                    emissive: isNight ? 0xfacc15 : 0x000000,
                    emissiveIntensity: isNight ? 1.0 : 0
                });
                const bulb = new THREE.Mesh(new THREE.SphereGeometry(0.18, 10, 10), bulbMat);
                bulb.position.set(lx, 3.85, lz);
                envGroup.add(post, bulb);
            });
        });
    }
};
