// objects/static/table.js — 3D модель ресурса
export function build(d = {}) {
    const g = new THREE.Group();
    const s = parseFloat(d.scale || 1.0);
    const col1 = new THREE.MeshStandardMaterial({ color: d.color || '#15803d', roughness: 0.7 });
    const col2 = new THREE.MeshStandardMaterial({ color: d.accent || '#78350f', roughness: 0.8 });
    const metalMat = new THREE.MeshStandardMaterial({ color: 0x1f2937, metalness: 0.7, roughness: 0.3 });

    const top = new THREE.Mesh(new THREE.BoxGeometry(1.5, 0.08, 0.9), col2);
                    top.position.y = 0.76;
                    g.add(top);
                    [[-0.65, -0.35], [0.65, -0.35], [-0.65, 0.35], [0.65, 0.35]].forEach(([lx, lz]) => {
                        const leg = new THREE.Mesh(new THREE.CylinderGeometry(0.04, 0.04, 0.76, 8), metalMat);
                        leg.position.set(lx, 0.38, lz);
                        g.add(leg);
                    });

    g.scale.set(s, s, s);
    g.traverse(m => { if (m.isMesh) { m.castShadow = true; m.receiveShadow = true; } });
    return g;
}

export default {
    id: 'table',
    category: 'static',
    staticType: 'table',
    radius: 0.85,
    build
};
