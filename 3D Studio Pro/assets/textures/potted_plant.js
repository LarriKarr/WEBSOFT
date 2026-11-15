// textures/potted_plant.js — Модуль ресурса 3D Studio Pro
export function createPottedPlant(x, z) {
            const g = new THREE.Group();
            const pot = new THREE.Mesh(new THREE.CylinderGeometry(0.24, 0.18, 0.36, 12), new THREE.MeshStandardMaterial({ color: 0x9a3412 }));
            pot.position.y = 0.18;
            const bush1 = new THREE.Mesh(new THREE.DodecahedronGeometry(0.32), new THREE.MeshStandardMaterial({ color: 0x15803d }));
            bush1.position.y = 0.52;
            const bush2 = new THREE.Mesh(new THREE.DodecahedronGeometry(0.24), new THREE.MeshStandardMaterial({ color: 0x22c55e }));
            bush2.position.set(0.1, 0.72, 0.05);
            g.add(pot, bush1, bush2);
            g.position.set(x, 0, z);
            g.traverse(m => { if (m.isMesh) m.castShadow = true; });
            envGroup.add(g);
            addStaticCollider(x, z, 0.55, 0.55);
        }

export default { id: 'potted_plant', create: createPottedPlant };
