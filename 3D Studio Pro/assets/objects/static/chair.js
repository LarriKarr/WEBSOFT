// objects/static/chair.js — 3D модель ресурса
export function build(d = {}) {
    const g = new THREE.Group();
    const s = parseFloat(d.scale || 1.0);
    const col1 = new THREE.MeshStandardMaterial({ color: d.color || '#15803d', roughness: 0.7 });
    const col2 = new THREE.MeshStandardMaterial({ color: d.accent || '#78350f', roughness: 0.8 });
    const metalMat = new THREE.MeshStandardMaterial({ color: 0x1f2937, metalness: 0.7, roughness: 0.3 });

    const seat = new THREE.Mesh(new THREE.BoxGeometry(0.46, 0.06, 0.46), col2);
                    seat.position.y = 0.46;
                    const back = new THREE.Mesh(new THREE.BoxGeometry(0.46, 0.48, 0.06), col2);
                    back.position.set(0, 0.72, -0.2);
                    g.add(seat, back);
                    [[-0.19, -0.19], [0.19, -0.19], [-0.19, 0.19], [0.19, 0.19]].forEach(([lx, lz]) => {
                        const leg = new THREE.Mesh(new THREE.CylinderGeometry(0.025, 0.025, 0.46, 8), metalMat);
                        leg.position.set(lx, 0.23, lz);
                        g.add(leg);
                    });

    g.scale.set(s, s, s);
    g.traverse(m => { if (m.isMesh) { m.castShadow = true; m.receiveShadow = true; } });
    return g;
}

export default {
    id: 'chair',
    category: 'static',
    staticType: 'chair',
    radius: 0.45,
    build
};
