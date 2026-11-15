// objects/static/monument.js — 3D модель ресурса
export function build(d = {}) {
    const g = new THREE.Group();
    const s = parseFloat(d.scale || 1.0);
    const col1 = new THREE.MeshStandardMaterial({ color: d.color || '#15803d', roughness: 0.7 });
    const col2 = new THREE.MeshStandardMaterial({ color: d.accent || '#78350f', roughness: 0.8 });
    const metalMat = new THREE.MeshStandardMaterial({ color: 0x1f2937, metalness: 0.7, roughness: 0.3 });

    const step = new THREE.Mesh(new THREE.BoxGeometry(1.7, 0.24, 1.7), new THREE.MeshStandardMaterial({ color: 0x475569 }));
                    step.position.y = 0.12;
                    const ped = new THREE.Mesh(new THREE.BoxGeometry(1.1, 1.2, 1.1), new THREE.MeshStandardMaterial({ color: 0x64748b }));
                    ped.position.y = 0.84;
                    const statueBody = new THREE.Mesh(new THREE.CylinderGeometry(0.22, 0.3, 1.35, 12), new THREE.MeshStandardMaterial({ color: 0xb45309, metalness: 0.75, roughness: 0.3 }));
                    statueBody.position.y = 2.1;
                    const statueHead = new THREE.Mesh(new THREE.SphereGeometry(0.18, 14, 14), new THREE.MeshStandardMaterial({ color: 0xb45309, metalness: 0.75, roughness: 0.3 }));
                    statueHead.position.y = 2.95;
                    g.add(step, ped, statueBody, statueHead);

    g.scale.set(s, s, s);
    g.traverse(m => { if (m.isMesh) { m.castShadow = true; m.receiveShadow = true; } });
    return g;
}

export default {
    id: 'monument',
    category: 'static',
    staticType: 'monument',
    radius: 0.45,
    build
};
