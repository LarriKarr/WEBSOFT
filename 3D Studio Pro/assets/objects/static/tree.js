// objects/static/tree.js — 3D модель ресурса
export function build(d = {}) {
    const g = new THREE.Group();
    const s = parseFloat(d.scale || 1.0);
    const col1 = new THREE.MeshStandardMaterial({ color: d.color || '#15803d', roughness: 0.7 });
    const col2 = new THREE.MeshStandardMaterial({ color: d.accent || '#78350f', roughness: 0.8 });
    const metalMat = new THREE.MeshStandardMaterial({ color: 0x1f2937, metalness: 0.7, roughness: 0.3 });

    const trunk = new THREE.Mesh(new THREE.CylinderGeometry(0.18, 0.28, 1.9, 10), col2);
                    trunk.position.y = 0.95;
                    const c1 = new THREE.Mesh(new THREE.DodecahedronGeometry(1.1), col1);
                    c1.position.y = 2.4;
                    const c2 = new THREE.Mesh(new THREE.DodecahedronGeometry(0.85), col1);
                    c2.position.set(0.32, 3.0, 0.18);
                    g.add(trunk, c1, c2);

    g.scale.set(s, s, s);
    g.traverse(m => { if (m.isMesh) { m.castShadow = true; m.receiveShadow = true; } });
    return g;
}

export default {
    id: 'tree',
    category: 'static',
    staticType: 'tree',
    radius: 0.45,
    build
};
