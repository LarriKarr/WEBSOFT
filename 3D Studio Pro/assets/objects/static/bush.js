// objects/static/bush.js — 3D модель ресурса
export function build(d = {}) {
    const g = new THREE.Group();
    const s = parseFloat(d.scale || 1.0);
    const col1 = new THREE.MeshStandardMaterial({ color: d.color || '#15803d', roughness: 0.7 });
    const col2 = new THREE.MeshStandardMaterial({ color: d.accent || '#78350f', roughness: 0.8 });
    const metalMat = new THREE.MeshStandardMaterial({ color: 0x1f2937, metalness: 0.7, roughness: 0.3 });

    const b1 = new THREE.Mesh(new THREE.DodecahedronGeometry(0.55), col1);
                    b1.position.y = 0.45;
                    const b2 = new THREE.Mesh(new THREE.DodecahedronGeometry(0.42), col1);
                    b2.position.set(0.32, 0.36, 0.15);
                    const b3 = new THREE.Mesh(new THREE.DodecahedronGeometry(0.38), col1);
                    b3.position.set(-0.3, 0.34, -0.12);
                    g.add(b1, b2, b3);

    g.scale.set(s, s, s);
    g.traverse(m => { if (m.isMesh) { m.castShadow = true; m.receiveShadow = true; } });
    return g;
}

export default {
    id: 'bush',
    category: 'static',
    staticType: 'bush',
    radius: 0.45,
    build
};
