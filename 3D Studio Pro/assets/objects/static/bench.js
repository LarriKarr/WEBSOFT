// objects/static/bench.js — 3D модель ресурса
export function build(d = {}) {
    const g = new THREE.Group();
    const s = parseFloat(d.scale || 1.0);
    const col1 = new THREE.MeshStandardMaterial({ color: d.color || '#15803d', roughness: 0.7 });
    const col2 = new THREE.MeshStandardMaterial({ color: d.accent || '#78350f', roughness: 0.8 });
    const metalMat = new THREE.MeshStandardMaterial({ color: 0x1f2937, metalness: 0.7, roughness: 0.3 });

    const seat = new THREE.Mesh(new THREE.BoxGeometry(1.6, 0.08, 0.5), col2);
                    seat.position.y = 0.44;
                    const back = new THREE.Mesh(new THREE.BoxGeometry(1.6, 0.42, 0.08), col2);
                    back.position.set(0, 0.72, -0.22);
                    back.rotation.x = -0.18;
                    [-0.68, 0.68].forEach(lx => {
                        const leg1 = new THREE.Mesh(new THREE.BoxGeometry(0.08, 0.44, 0.48), metalMat);
                        leg1.position.set(lx, 0.22, 0);
                        g.add(leg1);
                    });
                    g.add(seat, back);

    g.scale.set(s, s, s);
    g.traverse(m => { if (m.isMesh) { m.castShadow = true; m.receiveShadow = true; } });
    return g;
}

export default {
    id: 'bench',
    category: 'static',
    staticType: 'bench',
    radius: 0.85,
    build
};
